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

  // Mobile menu
  const navbar = document.querySelector("#navbar");
  const navToggle = document.querySelector("#nav-toggle");
  if (navbar && navToggle) {
    const setOpen = (open) => {
      navbar.classList.toggle("is-open", open);
      navToggle.setAttribute("aria-expanded", open ? "true" : "false");
      navToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    };
    navToggle.addEventListener("click", () => setOpen(!navbar.classList.contains("is-open")));
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") setOpen(false); });
    navbar.querySelectorAll(".navlinks a").forEach((a) => a.addEventListener("click", () => setOpen(false)));
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

  // Double-submit guard: after the first submit the buttons lock, so a double click
  // can never send the same form twice (e.g. registering the same Student ID twice).
  const unlock = (form) => {
    form.querySelectorAll("button[type=submit]").forEach((b) => {
      b.disabled = false;
      if (b.dataset.label) b.textContent = b.dataset.label;
    });
  };
  document.querySelectorAll("form[data-once]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (form.dataset.sent === "1") { e.preventDefault(); return; }
      form.dataset.sent = "1";
      form.querySelectorAll("button[type=submit]").forEach((b) => {
        b.dataset.label = b.textContent;
        if (b.dataset.busy) b.textContent = b.dataset.busy;
        // Disable after the browser has read the form, not before.
        setTimeout(() => { b.disabled = true; }, 0);
      });
    });
  });
  // Coming back with the Back button should not leave the form locked.
  window.addEventListener("pageshow", (e) => {
    if (!e.persisted) return;
    document.querySelectorAll("form[data-once]").forEach((form) => { form.dataset.sent = ""; unlock(form); });
  });

  // Admin: a row's Save button stays off until its name actually changes.
  document.querySelectorAll("form[data-rename]").forEach((form) => {
    const input = form.querySelector("input[name=name]");
    const save = form.querySelector("button[type=submit]");
    if (!input || !save) return;
    const sync = () => { save.disabled = input.value.trim() === (input.dataset.original || "").trim(); };
    input.addEventListener("input", sync);
    sync();
  });

  // Registration: live password hints. The server still does the real checking.
  const pw = document.querySelector("#password[data-min]");
  const pw2 = document.querySelector("#confirm_password");
  const pwHint = document.querySelector("#password-hint");
  const pw2Hint = document.querySelector("#confirm-hint");
  if (pw && pwHint) {
    const min = parseInt(pw.dataset.min, 10) || 6;
    const base = pwHint.textContent;
    const check = () => {
      const len = Array.from(pw.value).length;
      if (len === 0) { pwHint.textContent = base; pwHint.classList.remove("ok"); }
      else if (len < min) { pwHint.textContent = (min - len) + " more " + (min - len === 1 ? "character" : "characters") + " needed."; pwHint.classList.remove("ok"); }
      else { pwHint.textContent = "Good to go."; pwHint.classList.add("ok"); }
      if (pw2 && pw2Hint) {
        if (!pw2.value) { pw2Hint.textContent = ""; pw2Hint.classList.remove("ok"); }
        else if (pw2.value === pw.value) { pw2Hint.textContent = "Passwords match."; pw2Hint.classList.add("ok"); }
        else { pw2Hint.textContent = "Passwords do not match yet."; pw2Hint.classList.remove("ok"); }
      }
    };
    pw.addEventListener("input", check);
    if (pw2) pw2.addEventListener("input", check);
  }
});


// Hero text animation: a quiet typewriter rotation (skipped for reduced-motion users).
(() => {
  const el = document.querySelector("#hero-animated");
  if (!el) return;
  if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

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


// Verified Issues feed: reactions and comments.
(() => {
  const feed = document.querySelector(".feed");
  if (!feed) return;

  const endpoint = "issue_social.php";
  const csrf = feed.dataset.csrf || "";

  const request = async (url, options = {}) => {
    let res;
    try {
      res = await fetch(url, { credentials: "same-origin", ...options });
    } catch (e) {
      throw new Error("Could not reach the server. Check your connection and try again.");
    }
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok) throw new Error(data.error || "Something went wrong. Try again.");
    return data;
  };

  const post = (fields) =>
    request(endpoint, {
      method: "POST",
      headers: { "X-CSRF-Token": csrf },
      body: new URLSearchParams(fields),
    });

  const showNote = (card, message) => {
    const note = card.querySelector(".feed-note");
    if (!note) return;
    note.textContent = message || "";
    clearTimeout(note._timer);
    if (message) note._timer = setTimeout(() => (note.textContent = ""), 6000);
  };

  const setCount = (card, count) => {
    const el = card.querySelector(".comment-count");
    if (el) el.textContent = count;
  };

  const renderComment = (c) => {
    const row = document.createElement("div");
    row.className = "comment";
    row.dataset.id = c.id;

    const avatar = document.createElement("span");
    avatar.className = "comment-avatar";
    avatar.setAttribute("aria-hidden", "true");
    avatar.textContent = (c.name || "?").trim().charAt(0).toUpperCase();

    const main = document.createElement("div");
    main.className = "comment-main";

    const head = document.createElement("div");
    head.className = "comment-head";
    const name = document.createElement("strong");
    name.textContent = c.name;
    head.appendChild(name);
    if (c.role) {
      const role = document.createElement("span");
      role.className = "badge blue";
      role.textContent = c.role;
      head.appendChild(role);
    }
    const time = document.createElement("span");
    time.className = "comment-time";
    time.textContent = c.ago;
    head.appendChild(time);
    if (c.can_delete) {
      const del = document.createElement("button");
      del.type = "button";
      del.className = "comment-delete";
      del.textContent = "Delete";
      del.setAttribute("aria-label", "Delete comment by " + c.name);
      head.appendChild(del);
    }

    const text = document.createElement("p");
    text.className = "comment-text";
    text.textContent = c.body;

    main.append(head, text);
    row.append(avatar, main);
    return row;
  };

  const renderList = (card, comments) => {
    const list = card.querySelector(".feed-comment-list");
    list.replaceChildren();
    if (!comments.length) {
      const empty = document.createElement("p");
      empty.className = "comment-empty";
      empty.textContent = "No comments yet. Start the conversation.";
      list.appendChild(empty);
      return;
    }
    comments.forEach((c) => list.appendChild(renderComment(c)));
    list.scrollTop = list.scrollHeight;
  };

  const loadComments = async (card) => {
    const list = card.querySelector(".feed-comment-list");
    list.textContent = "Loading comments...";
    try {
      const data = await request(`${endpoint}?action=comments&issue_id=${encodeURIComponent(card.dataset.issue)}`);
      renderList(card, data.comments);
      setCount(card, data.count);
      card.dataset.loaded = "1";
    } catch (err) {
      list.replaceChildren();
      showNote(card, err.message);
    }
  };

  // Reactions
  feed.addEventListener("click", async (event) => {
    const btn = event.target.closest("button.react-btn");
    if (!btn) return;
    const card = btn.closest(".feed-card");
    const buttons = card.querySelectorAll("button.react-btn");
    buttons.forEach((b) => (b.disabled = true));
    try {
      const data = await post({ action: "react", issue_id: card.dataset.issue, reaction: btn.dataset.reaction });
      buttons.forEach((b) => {
        const key = b.dataset.reaction;
        const n = data.counts[key] || 0;
        b.querySelector(".react-count").textContent = n > 0 ? n : "";
        const active = data.mine === key;
        b.classList.toggle("is-active", active);
        b.setAttribute("aria-pressed", active ? "true" : "false");
      });
      showNote(card, "");
    } catch (err) {
      showNote(card, err.message);
    } finally {
      buttons.forEach((b) => (b.disabled = false));
    }
  });

  // Open / close the comment thread
  feed.addEventListener("click", (event) => {
    const toggle = event.target.closest(".comment-toggle");
    if (!toggle) return;
    const card = toggle.closest(".feed-card");
    const panel = card.querySelector(".feed-comments");
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      if (!card.dataset.loaded) loadComments(card);
      const input = panel.querySelector("input[name=body]");
      if (input) input.focus();
    }
  });

  // Send a comment
  feed.addEventListener("submit", async (event) => {
    const form = event.target.closest(".feed-comment-form");
    if (!form) return;
    event.preventDefault();
    const card = form.closest(".feed-card");
    const input = form.elements.body;
    const body = input.value.trim();
    if (!body) {
      showNote(card, "Write something before sending.");
      return;
    }
    const send = form.querySelector("button[type=submit]");
    send.disabled = true;
    try {
      const data = await post({ action: "comment", issue_id: card.dataset.issue, body });
      const list = card.querySelector(".feed-comment-list");
      const empty = list.querySelector(".comment-empty");
      if (empty) empty.remove();
      list.appendChild(renderComment(data.comment));
      list.scrollTop = list.scrollHeight;
      setCount(card, data.count);
      input.value = "";
      showNote(card, "");
    } catch (err) {
      showNote(card, err.message);
    } finally {
      send.disabled = false;
      input.focus();
    }
  });

  // Delete a comment
  feed.addEventListener("click", async (event) => {
    const del = event.target.closest(".comment-delete");
    if (!del) return;
    if (!confirm("Delete this comment?")) return;
    const card = del.closest(".feed-card");
    const row = del.closest(".comment");
    del.disabled = true;
    try {
      const data = await post({ action: "delete_comment", comment_id: row.dataset.id });
      row.remove();
      setCount(card, data.count);
      if (!card.querySelector(".comment")) renderList(card, []);
    } catch (err) {
      del.disabled = false;
      showNote(card, err.message);
    }
  });
})();
