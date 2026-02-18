document.addEventListener('DOMContentLoaded', () => {
  const modal = document.querySelector('#edit-modal');
  if (!modal) {
    return;
  }

  const idInput = modal.querySelector('#edit-id');
  const nameInput = modal.querySelector('#edit-name');
  const categoryInput = modal.querySelector('#edit-category');
  const quantityInput = modal.querySelector('#edit-quantity');
  const priceInput = modal.querySelector('#edit-price');
  const title = modal.querySelector('#edit-title');
  const meta = modal.querySelector('#edit-meta');

  const setValue = (input, value) => {
    if (!input) {
      return;
    }
    input.value = value === undefined ? '' : value;
  };

  const openModal = (data) => {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    setValue(idInput, data.id);
    setValue(nameInput, data.name);
    setValue(categoryInput, data.category);
    setValue(quantityInput, data.quantity);
    setValue(priceInput, data.price);

    if (title) {
      title.textContent = data.name ? `Edit ${data.name}` : 'Edit item';
    }
    if (meta) {
      meta.textContent = data.id ? `ID ${data.id} · Update the fields below.` : 'Update the fields below.';
    }

    if (nameInput) {
      nameInput.focus();
    }
  };

  const closeModal = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  };

  document.querySelectorAll('.js-edit').forEach((button) => {
    button.addEventListener('click', () => {
      openModal({
        id: button.dataset.id,
        name: button.dataset.name,
        category: button.dataset.category,
        quantity: button.dataset.quantity,
        price: button.dataset.price,
      });
    });
  });

  modal.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  if (modal.dataset.pending === '1') {
    openModal({
      id: modal.dataset.id,
      name: modal.dataset.name,
      category: modal.dataset.category,
      quantity: modal.dataset.quantity,
      price: modal.dataset.price,
    });
  }
});
