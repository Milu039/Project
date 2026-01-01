document.addEventListener("DOMContentLoaded", () => {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const rightContent = document.querySelector('.right-content');

    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        rightContent.classList.toggle('shifted');
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (
            sidebar.classList.contains('active') &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)
        ) {
            sidebar.classList.remove('active');
            rightContent.classList.remove('shifted');
        }
    });

    const links = document.querySelectorAll('.sidebar a');

    links.forEach(link => {
        // Remove previous active
        if (link.href === window.location.href) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
});
