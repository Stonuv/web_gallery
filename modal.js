document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('imageModal');
  const modalImage = document.getElementById('modalImage');

  if (!modal || !modalImage) return;

  function openModal(src) {
    modalImage.src = src;
    modal.classList.add('visible');
  }

  function closeModal() {
    modal.classList.remove('visible');
    modalImage.removeAttribute('src');
  }

  window.openModal = openModal;
  window.closeModal = closeModal;

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('visible')) {
      closeModal();
    }
  });
});
