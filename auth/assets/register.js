const password = document.getElementById("password");
const lengthReq = document.getElementById("length");
const upperReq = document.getElementById("uppercase");
const numberReq = document.getElementById("number");
const specialReq = document.getElementById("special");

// ✅ Password validation live feedback
password.addEventListener("input", () => {
  const val = password.value;

  // Length
  if (val.length >= 8) {
    lengthReq.textContent = "✅ At least 8 characters";
    lengthReq.classList.add("valid");
  } else {
    lengthReq.textContent = "❌ At least 8 characters";
    lengthReq.classList.remove("valid");
  }

  // Uppercase
  if (/[A-Z]/.test(val)) {
    upperReq.textContent = "✅ At least one uppercase letter";
    upperReq.classList.add("valid");
  } else {
    upperReq.textContent = "❌ At least one uppercase letter";
    upperReq.classList.remove("valid");
  }

  // Number
  if (/[0-9]/.test(val)) {
    numberReq.textContent = "✅ At least one number (0–9)";
    numberReq.classList.add("valid");
  } else {
    numberReq.textContent = "❌ At least one number (0–9)";
    numberReq.classList.remove("valid");
  }

  // Special character
  if (/[!@#$%^&*]/.test(val)) {
    specialReq.textContent = "✅ At least one special character (# $ % ^ & *)";
    specialReq.classList.add("valid");
  } else {
    specialReq.textContent = "❌ At least one special character (# $ % ^ & *)";
    specialReq.classList.remove("valid");
  }
});

// 👁️ Show/Hide password toggle
const togglePassword = document.getElementById("togglePassword");

togglePassword.addEventListener("click", () => {
  const isPassword = password.getAttribute("type") === "password";
  password.setAttribute("type", isPassword ? "text" : "password");
  togglePassword.textContent = isPassword ? "🙈" : "👁️";
});
