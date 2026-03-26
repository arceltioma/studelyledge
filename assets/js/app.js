document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.auto-hide').forEach(function (notice) {
        setTimeout(function () {
            notice.style.display = 'none';
        }, 3500);
    });
});