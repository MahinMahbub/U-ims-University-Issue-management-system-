document.addEventListener("DOMContentLoaded", () => {
  const root = document.documentElement;
  const toggle = document.querySelector("#theme-toggle");
  const themeKey = "uims-theme";

  const applyTheme = (dark) => {
    root.classList.toggle("dark", dark);
    root.dataset.theme = dark ? "dark" : "light";

    if (toggle) {
      toggle.classList.toggle("is-dark", dark);
      toggle.setAttribute("aria-label", dark ? "Switch to light theme" : "Switch to dark theme");
      toggle.setAttribute("title", dark ? "Switch to light theme" : "Switch to dark theme");
    }
  };

  let dark = false;
  try {
    dark = localStorage.getItem(themeKey) === "dark";
  } catch (e) {}
  applyTheme(dark);

  if (toggle) {
    toggle.addEventListener("click", () => {
      dark = !root.classList.contains("dark");
      applyTheme(dark);
      try { localStorage.setItem(themeKey, dark ? "dark" : "light"); } catch (e) {}
    });
  }

  document.querySelectorAll("[data-confirm]").forEach(btn => {
    btn.addEventListener("click", e => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  const file = document.querySelector("#evidence");
  const fileName = document.querySelector("#file-name");
  if (file && fileName) {
    file.addEventListener("change", () => {
      fileName.textContent = file.files.length ? file.files[0].name : "No file selected";
    });
  }
});


// Hero text animation: subtle formal typewriter rotation.
(() => {
  const el = document.querySelector("#hero-animated");
  if (!el) return;

  const phrases = ["Track progress.", "Build transparency.", "Improve the university."];
  let index = 0;
  let char = phrases[0].length;
  let deleting = true;

  const tick = () => {
    const phrase = phrases[index];
    if (deleting) {
      char--;
      el.textContent = phrase.slice(0, char);
      if (char <= 0) {
        deleting = false;
        index = (index + 1) % phrases.length;
      }
    } else {
      const next = phrases[index];
      char++;
      el.textContent = next.slice(0, char);
      if (char >= next.length) {
        deleting = true;
        setTimeout(tick, 1500);
        return;
      }
    }
    setTimeout(tick, deleting ? 55 : 80);
  };

  setTimeout(tick, 1800);
})();
