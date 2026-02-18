<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$pdo = null;
$dbError = null;

try {
    $pdo = require __DIR__ . '/db.php';
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if ($dbError) {
    $driverHint = '';
    if (stripos($dbError, 'driver') !== false) {
        $driverHint = 'Missing PDO MySQL driver.';
    }
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Inventory CRUD - Setup Required</title>
      <link rel="stylesheet" href="assets/styles.css">
    </head>
    <body>
      <main>
        <header class="page-header">
          <div>
            <p class="eyebrow">Inventory Studio</p>
            <h1>Database setup needed</h1>
            <p class="subtitle">Finish the database configuration to start using the CRUD app.</p>
          </div>
        </header>
        <section class="card">
          <div class="error">
            <?php echo e(trim($driverHint . ' ' . $dbError)); ?>
          </div>
          <p><strong>Linux (MariaDB):</strong> install the PDO MySQL extension and restart PHP/Apache.</p>
          <p>Example: <code>sudo apt install php-mysql</code> (or <code>php8.5-mysql</code>), then restart your web server.</p>
          <p><strong>Windows (XAMPP):</strong> open <code>xampp/php/php.ini</code> and enable:</p>
          <p><code>extension=pdo_mysql</code> and <code>extension=mysqli</code>, then restart Apache.</p>
          <p>Also confirm credentials in <code>config.php</code> and import <code>schema.sql</code>.</p>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(80) DEFAULT NULL,
        quantity INT DEFAULT NULL,
        price DECIMAL(10,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )'
);

$errors = [];
$notice = null;
$success = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($action === 'update' && !$id) {
            $errors[] = 'Missing item id for update.';
        }

        if ($name === '') {
            $errors[] = 'Item name is required.';
        }

        if ($quantity !== '' && (!ctype_digit($quantity) || (int) $quantity < 0)) {
            $errors[] = 'Quantity must be a non-negative integer.';
        }

        if ($price !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
            $errors[] = 'Price must be a valid amount (e.g., 12 or 12.50).';
        }

        if (!$errors) {
            $categoryValue = $category === '' ? null : $category;
            $quantityValue = $quantity === '' ? null : (int) $quantity;
            $priceValue = $price === '' ? null : $price;

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO items (name, category, quantity, price) VALUES (:name, :category, :quantity, :price)'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $categoryValue,
                    ':quantity' => $quantityValue,
                    ':price' => $priceValue,
                ]);
                header('Location: index.php?success=created');
                exit;
            }

            if ($action === 'update' && $id) {
                $stmt = $pdo->prepare(
                    'UPDATE items SET name = :name, category = :category, quantity = :quantity, price = :price WHERE id = :id'
                );
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $categoryValue,
                    ':quantity' => $quantityValue,
                    ':price' => $priceValue,
                    ':id' => $id,
                ]);
                header('Location: index.php?success=updated');
                exit;
            }
        }
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
            $stmt->execute([':id' => $id]);
        }
        header('Location: index.php?success=deleted');
        exit;
    }
}

$editItem = null;
$isEditing = false;
$editingId = null;
if (($_GET['action'] ?? '') === 'edit') {
    $editId = (int) ($_GET['id'] ?? 0);
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, category, quantity, price FROM items WHERE id = :id');
        $stmt->execute([':id' => $editId]);
        $editItem = $stmt->fetch() ?: null;
        if (!$editItem) {
            $notice = 'That item no longer exists.';
        } else {
            $isEditing = true;
            $editingId = (int) $editItem['id'];
        }
    }
}

if (!$isEditing && ($_POST['action'] ?? '') === 'update') {
    $isEditing = true;
    $editingId = (int) ($_POST['id'] ?? 0);
}

$items = $pdo->query(
    'SELECT id, name, category, quantity, price, created_at, updated_at FROM items ORDER BY id DESC'
)->fetchAll();

$totalItems = count($items);
$totalQuantity = 0;
foreach ($items as $item) {
    $totalQuantity += (int) ($item['quantity'] ?? 0);
}

$formName = $editItem['name'] ?? ($_POST['name'] ?? '');
$formCategory = $editItem['category'] ?? ($_POST['category'] ?? '');
$formQuantity = $editItem['quantity'] ?? ($_POST['quantity'] ?? '');
$formPrice = $editItem['price'] ?? ($_POST['price'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventory CRUD</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <main>
    <header class="page-header">
      <div>
        <p class="eyebrow">Inventory Studio</p>
        <h1>CRUD Inventory Manager</h1>
        <p class="subtitle">Create, view, update, and delete items in your MySQL database with a polished interface.</p>
      </div>
      <div class="header-metrics">
        <div class="metric">
          <span>Total items</span>
          <strong><?php echo $totalItems; ?></strong>
        </div>
        <div class="metric">
          <span>Total quantity</span>
          <strong><?php echo $totalQuantity; ?></strong>
        </div>
      </div>
    </header>

    <?php if ($notice) : ?>
      <div class="notice"><?php echo e($notice); ?></div>
    <?php endif; ?>

    <?php if ($success) : ?>
      <div class="notice">
        <?php if ($success === 'created') : ?>Item created successfully.
        <?php elseif ($success === 'updated') : ?>Item updated successfully.
        <?php elseif ($success === 'deleted') : ?>Item deleted successfully.
        <?php else : ?>Action completed.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($errors) : ?>
      <div class="error">
        <?php echo implode('<br>', array_map('e', $errors)); ?>
      </div>
    <?php endif; ?>

    <section class="card">
      <div class="card-header">
        <h2><?php echo $isEditing ? 'Edit item' : 'Add new item'; ?></h2>
        <?php if ($isEditing) : ?>
          <a class="ghost-link" href="index.php">Cancel edit</a>
        <?php endif; ?>
      </div>
      <form class="form-grid" method="post">
        <input type="hidden" name="action" value="<?php echo $isEditing ? 'update' : 'create'; ?>">
        <?php if ($isEditing) : ?>
          <input type="hidden" name="id" value="<?php echo (int) $editingId; ?>">
        <?php endif; ?>

        <label>
          <span>Item name</span>
          <input name="name" required value="<?php echo e((string) $formName); ?>" placeholder="e.g. Studio lamp">
        </label>
        <label>
          <span>Category</span>
          <input name="category" value="<?php echo e((string) $formCategory); ?>" placeholder="e.g. Lighting">
        </label>
        <label>
          <span>Quantity</span>
          <input name="quantity" type="number" min="0" value="<?php echo e((string) $formQuantity); ?>" placeholder="0">
        </label>
        <label>
          <span>Price</span>
          <input name="price" type="number" min="0" step="0.01" value="<?php echo e((string) $formPrice); ?>" placeholder="0.00">
        </label>
        <div class="form-actions">
          <button class="primary" type="submit">
            <?php echo $isEditing ? 'Update item' : 'Create item'; ?>
          </button>
          <?php if (!$isEditing) : ?>
            <button class="ghost" type="reset">Clear</button>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="table-wrap">
      <div class="table-header">
        <h2>Current inventory</h2>
        <span><?php echo $totalItems; ?> items</span>
      </div>
      <?php if (!$items) : ?>
        <div class="empty-state">No items yet. Add your first record using the form above.</div>
      <?php else : ?>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Category</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item) : ?>
              <tr>
                <td><?php echo e($item['name']); ?></td>
                <td>
                  <?php if (!empty($item['category'])) : ?>
                    <span class="pill"><?php echo e($item['category']); ?></span>
                  <?php else : ?>
                    —
                  <?php endif; ?>
                </td>
                <td class="qty"><?php echo $item['quantity'] !== null ? (int) $item['quantity'] : '—'; ?></td>
                <td>
                  <?php if ($item['price'] !== null) : ?>
                    $<?php echo number_format((float) $item['price'], 2); ?>
                  <?php else : ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                <td>
                  <div class="row-actions">
                    <a class="btn ghost" href="index.php?action=edit&id=<?php echo (int) $item['id']; ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this item?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                      <button class="btn primary" type="submit">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
