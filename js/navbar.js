document.addEventListener("DOMContentLoaded", function () {
  const isLoggedIn = localStorage.getItem("isLoggedIn");
  const username = localStorage.getItem("currentUser");
  const loginLink = document.getElementById("loginLink");
  const userDropdown = document.getElementById("userDropdown");
  const loginMenu = document.getElementById("loginMenu");

  if (isLoggedIn && username) {
    loginLink.textContent = username; // Hiển thị username
    loginLink.href = "#";
    loginLink.classList.add("dropdown-toggle");
    loginLink.setAttribute("data-bs-toggle", "dropdown");
    userDropdown.style.display = "block";
  } else {
    loginLink.textContent = "Đăng Nhập";
    loginLink.href = "DangNhap.html";
    userDropdown.style.display = "none";
  }

  loginMenu.addEventListener("mouseenter", function () {
    if (isLoggedIn) userDropdown.classList.add("show");
  });
  loginMenu.addEventListener("mouseleave", function () {
    userDropdown.classList.remove("show");
  });
});

function logout() {
  localStorage.removeItem("isLoggedIn");
  localStorage.removeItem("currentUser");
  alert("Đã đăng xuất!");
  window.location.href = "index.html";
}
