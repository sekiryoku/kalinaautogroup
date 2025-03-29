document.addEventListener("DOMContentLoaded", function () {
    const modalOverlay = document.getElementById("modalOverlay");
    const closeModalBtn = document.getElementById("modalClose");

    function openModal() {
        modalOverlay.classList.add("active");
    }

    function closeModal() {
        modalOverlay.classList.remove("active");
    }

    document.body.addEventListener("click", function (event) {
        if (event.target.closest("[data-modal]")) {
            openModal();
        }
    });

    closeModalBtn.addEventListener("click", closeModal);

    modalOverlay.addEventListener("click", function (event) {
        if (event.target === modalOverlay) {
            closeModal();
        }
    });
});