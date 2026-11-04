document.addEventListener("DOMContentLoaded", function () {
  const topBar = document.getElementById("iwd-top-bar");
  if (!topBar) return;

  window.addEventListener("scroll", function () {
    if (window.scrollY > 100) {
      topBar.classList.add("hidden");
    } else if (window.scrollY === 0) {
      // only show again if user is *all the way back up*
      topBar.classList.remove("hidden");
    }
  });
});
