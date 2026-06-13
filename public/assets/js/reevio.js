(() => {
  function makeToggleInteractive(button) {
    if (button.dataset.reevioToggleReady === "true") return;

    button.dataset.reevioToggleReady = "true";

    if (!button.hasAttribute("aria-pressed")) {
      const isInitiallyOn =
        button.className.includes("bg-primary") ||
        button.querySelector("span")?.className.includes("right-1");

      button.setAttribute("aria-pressed", isInitiallyOn ? "true" : "false");
    }

    button.addEventListener("click", () => {
      const nextState = button.getAttribute("aria-pressed") !== "true";
      button.setAttribute("aria-pressed", nextState ? "true" : "false");
    });
  }

  function makeCardClickable(element) {
    if (element.dataset.reevioClickableReady === "true") return;
    if (element.closest("a")) return;

    element.dataset.reevioClickableReady = "true";
    element.setAttribute("role", "link");
    element.setAttribute("tabindex", "0");

    const go = () => {
      const target = element.getAttribute("data-href") || "#";
      if (target && target !== "#") {
        window.location.href = target;
      }
    };

    element.addEventListener("click", (event) => {
      if (event.target.closest("a, button, input, textarea, select, label")) return;
      go();
    });

    element.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        go();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('button[aria-label$="toggle"]').forEach(makeToggleInteractive);

    const clickableSelectors = [
      "article.cursor-pointer",
      "article.group",
      ".friend-card",
      ".poster-card",
      ".list-card",
      ".user-card",
      ".watchlist-card",
      ".review-card",
      ".follower-card"
    ];

    document.querySelectorAll(clickableSelectors.join(",")).forEach((element) => {
      const hasMeaningfulButton = element.querySelector("button");
      const isSmallActionCard = element.matches("article.group, article.cursor-pointer, .friend-card, .poster-card");
      if (!hasMeaningfulButton || isSmallActionCard) {
        makeCardClickable(element);
      }
    });

    document.querySelectorAll("[data-href]").forEach(makeCardClickable);
  });
})();


/* Mobile quality-of-life: keep active sidebar icon visible in bottom nav. */
document.addEventListener("DOMContentLoaded", () => {
  if (window.matchMedia("(max-width: 900px)").matches) {
    const activeNavItem = document.querySelector(
      "aside a[class*='text-primary'], aside a[class*='bg-surface-variant']"
    );
    if (activeNavItem && typeof activeNavItem.scrollIntoView === "function") {
      activeNavItem.scrollIntoView({ inline: "center", block: "nearest" });
    }
  }
});


/* Mobile hamburger navigation */
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.querySelector("body > aside, aside[class*='w-64'][class*='fixed'], aside[class*='left-0'][class*='top-0']");
  if (!sidebar || document.querySelector(".reevio-mobile-menu-toggle")) return;

  const toggle = document.createElement("button");
  toggle.className = "reevio-mobile-menu-toggle";
  toggle.type = "button";
  toggle.setAttribute("aria-label", "Open navigation menu");
  toggle.setAttribute("aria-expanded", "false");
  toggle.innerHTML = '<span class="material-symbols-outlined">menu</span>';

  const overlay = document.createElement("button");
  overlay.className = "reevio-mobile-menu-overlay";
  overlay.type = "button";
  overlay.setAttribute("aria-label", "Close navigation menu");

  document.body.prepend(overlay);
  document.body.prepend(toggle);

  const setMenuOpen = (open) => {
    document.body.classList.toggle("reevio-menu-open", open);
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Close navigation menu" : "Open navigation menu");
    const icon = toggle.querySelector(".material-symbols-outlined");
    if (icon) icon.textContent = open ? "close" : "menu";

    if (window.matchMedia("(max-width: 900px)").matches) {
      document.body.style.overflow = open ? "hidden" : "";
    }
  };

  toggle.addEventListener("click", () => {
    setMenuOpen(!document.body.classList.contains("reevio-menu-open"));
  });

  overlay.addEventListener("click", () => setMenuOpen(false));

  sidebar.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => setMenuOpen(false));
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setMenuOpen(false);
  });

  window.addEventListener("resize", () => {
    if (!window.matchMedia("(max-width: 900px)").matches) {
      setMenuOpen(false);
      document.body.style.overflow = "";
    }
  });
});


/* Mobile top-right actions inside hamburger menu */
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.querySelector("body > aside, aside[class*='w-64'][class*='fixed'], aside[class*='left-0'][class*='top-0']");
  if (!sidebar || sidebar.querySelector(".reevio-mobile-menu-actions")) return;

  const actions = document.createElement("div");
  actions.className = "reevio-mobile-menu-actions";
  actions.innerHTML = `
    <span class="reevio-mobile-menu-actions__label">Options</span>
    <div class="reevio-mobile-menu-actions__buttons">
      <button class="reevio-mobile-menu-actions__button" type="button" aria-label="Settings">
        <span class="material-symbols-outlined">settings</span>
      </button>
      <button class="reevio-mobile-menu-actions__button" type="button" aria-label="Notifications">
        <span class="material-symbols-outlined">notifications</span>
        <span class="reevio-mobile-menu-actions__dot"></span>
      </button>
      <button class="reevio-mobile-menu-actions__button" type="button" aria-label="Account">
        <span class="material-symbols-outlined">account_circle</span>
      </button>
    </div>
  `;

  const firstSidebarBlock = sidebar.querySelector(":scope > div:first-child");
  if (firstSidebarBlock) {
    firstSidebarBlock.appendChild(actions);
  } else {
    sidebar.appendChild(actions);
  }
});

/* Shared top-right and mobile menu actions navigation */
document.addEventListener("DOMContentLoaded", () => {
  const targetByLabel = {
    settings: "/settings",
    notifications: "/notifications",
    account: "/profile"
  };

  document.addEventListener("click", (event) => {
    const button = event.target.closest("button[aria-label]");
    if (!button || !button.classList.contains("reevio-mobile-menu-actions__button")) return;

    const label = button.getAttribute("aria-label").toLowerCase();
    const target = targetByLabel[label];

    if (target) {
      window.location.href = target;
    }
  });
});

/* Password reveal buttons */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("button[data-password-toggle]").forEach((button) => {
    if (button.dataset.reevioPasswordReady === "true") return;
    button.dataset.reevioPasswordReady = "true";

    const wrapper = button.closest(".relative, .group, div") || button.parentElement;
    const input = wrapper ? wrapper.querySelector('input[name="password"], input[name="password2"], input[type="password"], input[data-password-field]') : null;
    const icon = button.querySelector(".material-symbols-outlined");

    if (!input) return;

    button.addEventListener("click", () => {
      const isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";
      button.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
      if (icon) icon.textContent = isHidden ? "visibility" : "visibility_off";
    });
  });
});



/* Reevio page-to-page navigation helpers */
document.addEventListener("DOMContentLoaded", () => {
  const searchPage = document.querySelector(".reevio-page-search_empty");

  if (searchPage) {
    const input = searchPage.querySelector('input[type="text"], input[type="search"]');
    const buttons = [...searchPage.querySelectorAll("button")];
    const targetByLabel = {
      all: "/search",
      films: "/search-films",
      crew: "/search-crew",
      users: "/search-users",
      lists: "/search-lists"
    };

    let selectedTarget = "/search";

    const goToSearch = (target) => {
      const query = input ? input.value.trim() : "";
      const url = query ? `${target}?q=${encodeURIComponent(query)}` : target;
      window.location.href = url;
    };

    buttons.forEach((button) => {
      const label = button.textContent.trim().toLowerCase();

      if (!targetByLabel[label]) return;

      button.type = "button";
      button.addEventListener("click", () => {
        selectedTarget = targetByLabel[label];
        goToSearch(selectedTarget);
      });
    });

    if (input) {
      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          goToSearch(selectedTarget);
        }
      });
    }
  }

  const logSearchEmpty = document.querySelector(".reevio-page-log_search_empty");

  if (logSearchEmpty) {
    const input = logSearchEmpty.querySelector('input[type="text"], input[type="search"]');

    if (input) {
      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          const query = input.value.trim();
          window.location.href = query
            ? `/log-search-results?q=${encodeURIComponent(query)}`
            : "/log-search-results";
        }
      });
    }
  }

  const logSearchResults = document.querySelector(".reevio-page-log_search_results");

  if (logSearchResults) {
    logSearchResults.querySelectorAll("[data-log-select]").forEach((button) => {
      button.addEventListener("click", () => {
        window.location.href = button.getAttribute("data-log-select") || "/log-selected";
      });
    });
  }
});

/* Follow / unfollow buttons */
document.addEventListener("click", async (event) => {
  const button = event.target.closest("[data-follow-button]");

  if (!button) return;

  event.preventDefault();
  event.stopPropagation();

  function setFollowButtonState(targetButton, isFollowing) {
    targetButton.dataset.following = isFollowing ? "1" : "0";
    targetButton.textContent = isFollowing ? "Following" : "Follow";

    const followingClass =
      "shrink-0 rounded-full border border-secondary/40 px-6 py-2.5 font-label text-sm font-bold text-secondary transition-colors hover:bg-secondary/10";

    const followClass =
      "shrink-0 rounded-full bg-gradient-to-r from-primary to-primary-container px-6 py-2.5 font-label text-sm font-bold text-on-primary shadow-[0_5px_15px_rgba(255,215,155,0.2)] transition-opacity hover:opacity-90";

    targetButton.className = isFollowing ? followingClass : followClass;
  }

  function setAllButtonsForUser(userId, isFollowing) {
    document.querySelectorAll(`[data-follow-button][data-user-id="${userId}"]`).forEach((targetButton) => {
      setFollowButtonState(targetButton, isFollowing);
    });
  }

  const userId = Number(button.dataset.userId);
  const nextFollowState = button.dataset.following !== "1";

  if (!userId || button.dataset.loading === "1") return;

  const previousState = button.dataset.following === "1";

  button.dataset.loading = "1";
  button.disabled = true;
  button.textContent = nextFollowState ? "Following..." : "Unfollowing...";

  try {
    const response = await fetch("/api-users-follow", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({
        user_id: userId,
        follow: nextFollowState
      })
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Follow action failed.");
    }

    setAllButtonsForUser(userId, Boolean(payload.following));
  } catch (error) {
    console.error(error);
    setFollowButtonState(button, previousState);
    button.title = "Action failed. Try again.";
  } finally {
    button.disabled = false;
    button.dataset.loading = "0";
  }
});





/* Settings avatar upload */
document.addEventListener("DOMContentLoaded", () => {
  const input = document.querySelector("#settings-avatar-input");
  const preview = document.querySelector("#settings-avatar-preview");
  const status = document.querySelector("#settings-avatar-status");
  const maxBytes = 2 * 1024 * 1024;
  const maxSize = 1024;

  if (!input || input.dataset.reevioAvatarReady === "1") return;
  input.dataset.reevioAvatarReady = "1";

  function setAvatarStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function updateAvatarPreview(url) {
    if (!preview) return;
    preview.innerHTML = `<img alt="Profile avatar" class="h-full w-full object-cover" src="${url}" />`;

    document.querySelectorAll("[data-nav-account-avatar]").forEach((target) => {
      target.innerHTML = `<img src="${url}" alt="Account avatar" class="h-7 w-7 rounded-full object-cover" />`;
    });
  }

  function validateImage(file) {
    return new Promise((resolve, reject) => {
      if (!file) {
        reject(new Error("Choose an image first."));
        return;
      }

      const allowed = ["image/jpeg", "image/png", "image/webp"];

      if (!allowed.includes(file.type)) {
        reject(new Error("Avatar must be JPG, PNG or WEBP."));
        return;
      }

      if (file.size > maxBytes) {
        reject(new Error("Avatar must be smaller than 2 MB."));
        return;
      }

      const img = new Image();
      const url = URL.createObjectURL(file);

      img.onload = () => {
        URL.revokeObjectURL(url);
        if (img.width > maxSize || img.height > maxSize) {
          reject(new Error("Avatar resolution cannot exceed 1024 × 1024 px."));
          return;
        }

        resolve();
      };

      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error("Avatar must be a valid image."));
      };

      img.src = url;
    });
  }

  input.addEventListener("change", async () => {
    const file = input.files?.[0];

    try {
      await validateImage(file);
    } catch (error) {
      setAvatarStatus(error.message || "Invalid avatar.", true);
      input.value = "";
      return;
    }

    const formData = new FormData();
    formData.append("avatar", file);

    setAvatarStatus("Uploading avatar...");

    try {
      const response = await fetch("/api-settings-avatar", {
        method: "POST",
        headers: {
          "Accept": "application/json"
        },
        body: formData
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || payload.error || "Could not upload avatar.");
      }

      const avatarUrl = payload.avatar_url || payload.user?.avatar_url;
      if (avatarUrl) {
        updateAvatarPreview(avatarUrl);
      }

      setAvatarStatus("Avatar updated.");
    } catch (error) {
      setAvatarStatus(error.message || "Could not upload avatar.", true);
    } finally {
      input.value = "";
    }
  });
});



/* Settings profile save */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#settings-profile-form");

  if (!form || form.dataset.reevioSettingsReady === "1") return;

  form.dataset.reevioSettingsReady = "1";

  const usernameInput = form.querySelector("#settings-username");
  const bioInput = form.querySelector("#settings-bio");
  const submitButton = form.querySelector("#settings-save-button");
  const status = form.querySelector("#settings-save-status");
  const bioCounter = form.querySelector("#settings-bio-counter");
  const bioMaxLength = 64;

  function updateBioCounter() {
    if (!bioInput || !bioCounter) return;

    if (bioInput.value.length > bioMaxLength) {
      bioInput.value = bioInput.value.slice(0, bioMaxLength);
    }

    bioCounter.textContent = `${bioInput.value.length}/${bioMaxLength} characters`;
  }

  bioInput?.addEventListener("input", updateBioCounter);
  updateBioCounter();

  function setStatus(message, isError = false) {
    if (!status) return;

    status.textContent = message;
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const username = usernameInput?.value.trim() || "";
    const bio = bioInput?.value.trim() || "";

    if (!username) {
      setStatus("Username is required.", true);
      usernameInput?.focus();
      return;
    }

    if (bio.length > bioMaxLength) {
      setStatus("Bio cannot be longer than 64 characters.", true);
      bioInput?.focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.classList.add("opacity-70");
    setStatus("Saving...");

    try {
      const response = await fetch("/api-settings-profile", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ username, bio })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || payload.error || "Could not save changes.");
      }

      if (payload.user?.username && usernameInput) {
        usernameInput.value = payload.user.username;
      }

      if (typeof payload.user?.bio === "string" && bioInput) {
        bioInput.value = payload.user.bio;
      }

      setStatus("Changes saved.");
    } catch (error) {
      setStatus(error.message || "Could not save changes.", true);
    } finally {
      submitButton.disabled = false;
      submitButton.classList.remove("opacity-70");
    }
  });
});




/* Diary log delete */
document.addEventListener("click", async (event) => {
  const button = event.target.closest("[data-delete-log]");

  if (!button) return;

  event.preventDefault();

  const logId = Number(button.dataset.logId);

  if (!logId || button.dataset.loading === "1") return;

  const confirmed = window.confirm("Delete this log? This will remove it from your diary.");
  if (!confirmed) return;

  button.dataset.loading = "1";
  button.disabled = true;
  const originalText = button.innerHTML;
  button.innerHTML = '<span class="material-symbols-outlined text-[18px]">hourglass_top</span>Deleting...';

  try {
    const response = await fetch("/api-diary-delete-log", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({ log_id: logId })
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Could not delete log.");
    }

    window.location.href = payload.redirect || "/profile-diary";
  } catch (error) {
    alert(error.message || "Could not delete log.");
    button.disabled = false;
    button.dataset.loading = "0";
    button.innerHTML = originalText;
  }
});




/* Log selected film */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#log-selected-form");
  if (!form || form.dataset.reevioLogSelectedReady === "1") return;

  form.dataset.reevioLogSelectedReady = "1";

  const filmId = Number(form.dataset.filmId);
  const dateButton = document.querySelector("#log-date-button");
  const dateLabel = document.querySelector("#log-date-label");
  const rewatchToggle = document.querySelector("#log-rewatch-toggle");
  const rewatchKnob = document.querySelector("#log-rewatch-knob");
  const rewatchLabel = document.querySelector("#log-rewatch-label");
  const ratingControl = document.querySelector("#log-rating-control");
  const ratingLabel = document.querySelector("#log-rating-label");
  const reviewInput = document.querySelector("#log-review");
  const saveButton = document.querySelector("#log-save-button");
  const saveStatus = document.querySelector("#log-save-status");

  const overlay = document.querySelector("#log-calendar-overlay");
  const calendarMonth = document.querySelector("#log-calendar-month");
  const calendarDays = document.querySelector("#log-calendar-days");
  const calendarPrev = document.querySelector("#log-calendar-prev");
  const calendarNext = document.querySelector("#log-calendar-next");
  const calendarClose = document.querySelector("#log-calendar-close");
  const calendarCancel = document.querySelector("#log-calendar-cancel");
  const calendarDone = document.querySelector("#log-calendar-done");
  const calendarConfirm = document.querySelector("#log-calendar-confirm");

  const today = new Date();
  let selectedDate = parseIsoDate(form.dataset.initialDate) || today;
  let calendarView = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
  let rating = Number(form.dataset.initialRating || 3.5);
  let isRewatch = false;

  function parseIsoDate(value) {
    if (!value) return null;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return null;
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  }

  function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function formatDate(date) {
    return date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric"
    });
  }

  function setStatus(message, isError = false) {
    if (!saveStatus) return;
    saveStatus.textContent = message;
    saveStatus.classList.toggle("text-red-300", isError);
    saveStatus.classList.toggle("text-primary", !isError && message.length > 0);
    saveStatus.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function renderDate() {
    if (!dateLabel) return;

    const isToday = toIsoDate(selectedDate) === toIsoDate(today);
    dateLabel.textContent = isToday ? "Today" : formatDate(selectedDate);
  }

  function renderRewatch() {
    if (rewatchLabel) rewatchLabel.textContent = isRewatch ? "Yes" : "No";

    if (rewatchToggle) {
      rewatchToggle.setAttribute("aria-pressed", isRewatch ? "true" : "false");
      rewatchToggle.classList.toggle("bg-primary", isRewatch);
      rewatchToggle.classList.toggle("bg-surface-container-highest", !isRewatch);
    }

    if (rewatchKnob) {
      rewatchKnob.className = isRewatch
        ? "absolute right-1 top-1 h-5 w-5 rounded-full bg-on-primary shadow-sm transition-all"
        : "absolute left-1 top-1 h-5 w-5 rounded-full bg-on-surface-variant shadow-sm transition-all";
    }
  }

  function renderRating() {
    rating = Math.max(0.5, Math.min(5, Math.round(rating * 2) / 2));
    if (ratingLabel) ratingLabel.textContent = `${rating.toFixed(1)} / 5.0`;

    ratingControl?.querySelectorAll("[data-rating-star]").forEach((button) => {
      const starNumber = Number(button.dataset.ratingStar);
      const icon = button.querySelector(".material-symbols-outlined");
      if (!icon) return;

      icon.className = "material-symbols-outlined text-5xl";
      if (rating >= starNumber) {
        icon.textContent = "star";
        icon.classList.add("star-glow", "text-primary", "filled");
      } else if (rating >= starNumber - 0.5) {
        icon.textContent = "star_half";
        icon.classList.add("star-glow", "text-primary", "filled");
      } else {
        icon.textContent = "star";
        icon.classList.add("text-surface-container-highest");
      }
    });
  }

  function openCalendar() {
    if (!overlay) return;
    calendarView = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
    overlay.classList.remove("hidden");
    overlay.classList.add("flex");
    document.body.style.overflow = "hidden";
    renderCalendar();
  }

  function closeCalendar() {
    if (!overlay) return;
    overlay.classList.add("hidden");
    overlay.classList.remove("flex");
    document.body.style.overflow = "";
  }

  function renderCalendar() {
    if (!calendarDays || !calendarMonth) return;

    calendarMonth.textContent = calendarView.toLocaleDateString("en-US", {
      month: "long",
      year: "numeric"
    });

    calendarDays.innerHTML = "";

    const year = calendarView.getFullYear();
    const month = calendarView.getMonth();
    const firstDay = new Date(year, month, 1);
    const startDate = new Date(year, month, 1 - firstDay.getDay());

    for (let index = 0; index < 42; index += 1) {
      const date = new Date(startDate);
      date.setDate(startDate.getDate() + index);

      const isCurrentMonth = date.getMonth() === month;
      const isSelected = toIsoDate(date) === toIsoDate(selectedDate);
      const isToday = toIsoDate(date) === toIsoDate(today);

      const button = document.createElement("button");
      button.type = "button";
      button.dataset.calendarDate = toIsoDate(date);
      button.textContent = String(date.getDate());

      let className = "flex h-11 items-center justify-center rounded-full transition-colors ";
      if (!isCurrentMonth) {
        className += "text-on-surface-variant/40 ";
      } else {
        className += "text-on-surface hover:bg-surface-variant ";
      }

      if (isToday && !isSelected) {
        className += "border border-primary/30 font-bold text-primary text-glow ";
      }

      if (isSelected) {
        className += "bg-primary font-bold text-on-primary shadow-[0_0_14px_rgba(255,215,155,0.42)] ";
      }

      button.className = className;

      button.addEventListener("click", () => {
        selectedDate = parseIsoDate(button.dataset.calendarDate) || selectedDate;
        renderCalendar();
      });

      calendarDays.appendChild(button);
    }
  }

  dateButton?.addEventListener("click", openCalendar);
  calendarClose?.addEventListener("click", closeCalendar);
  calendarCancel?.addEventListener("click", closeCalendar);
  calendarDone?.addEventListener("click", () => {
    renderDate();
    closeCalendar();
  });
  calendarConfirm?.addEventListener("click", () => {
    renderDate();
    closeCalendar();
  });
  calendarPrev?.addEventListener("click", () => {
    calendarView = new Date(calendarView.getFullYear(), calendarView.getMonth() - 1, 1);
    renderCalendar();
  });
  calendarNext?.addEventListener("click", () => {
    calendarView = new Date(calendarView.getFullYear(), calendarView.getMonth() + 1, 1);
    renderCalendar();
  });

  rewatchToggle?.addEventListener("click", () => {
    isRewatch = !isRewatch;
    renderRewatch();
  });

  ratingControl?.querySelectorAll("[data-rating-star]").forEach((button) => {
    button.addEventListener("click", (event) => {
      const starNumber = Number(button.dataset.ratingStar);
      const rect = button.getBoundingClientRect();
      const half = event.clientX - rect.left <= rect.width / 2;
      rating = half ? starNumber - 0.5 : starNumber;
      renderRating();
    });
  });

  saveButton?.addEventListener("click", async () => {
    if (!filmId) {
      setStatus("Film could not be resolved.", true);
      return;
    }

    saveButton.disabled = true;
    saveButton.classList.add("opacity-70");
    setStatus("Saving log...");

    try {
      const response = await fetch("/api-log-save", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({
          film_id: filmId,
          watched_on: toIsoDate(selectedDate),
          rating,
          is_rewatch: isRewatch,
          review: reviewInput ? reviewInput.value.trim() : ""
        })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Could not save log.");
      }

      setStatus("Saved.");
      window.location.href = payload.redirect || "/profile-diary";
    } catch (error) {
      setStatus(error.message || "Could not save log.", true);
      saveButton.disabled = false;
      saveButton.classList.remove("opacity-70");
    }
  });

  renderDate();
  renderRewatch();
  renderRating();
});




/* Create list form */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("#create-list-form");

  if (!form || form.dataset.reevioCreateListReady === "1") return;

  form.dataset.reevioCreateListReady = "1";

  const titleInput = form.querySelector("#list-name");
  const descriptionInput = form.querySelector("#list-desc");
  const publicInput = form.querySelector("#privacy-toggle");
  const rankedInput = form.querySelector("#ranked-toggle");
  const submitButton = form.querySelector("#create-list-submit");
  const status = form.querySelector("#create-list-status");
  const sourceFilmId = Number(form.dataset.sourceFilmId || 0);

  function setStatus(message, isError = false) {
    if (!status) return;

    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const title = titleInput?.value.trim() || "";
    const description = descriptionInput?.value.trim() || "";

    if (!title) {
      setStatus("List name is required.", true);
      titleInput?.focus();
      return;
    }

    submitButton.disabled = true;
    submitButton.classList.add("opacity-70");
    setStatus("Creating list...");

    try {
      const response = await fetch("/api-lists-create", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({
          title,
          description,
          is_public: publicInput ? publicInput.checked : true,
          is_ranked: rankedInput ? rankedInput.checked : false,
          film_id: sourceFilmId || null
        })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || (!payload.created && !payload.id)) {
        throw new Error(payload.error || payload.message || "Could not create list.");
      }

      setStatus("List created.");
      window.location.href = payload.redirect || (payload.id ? `/list-details?id=${payload.id}` : "/profile-lists");
    } catch (error) {
      setStatus(error.message || "Could not create list.", true);
      submitButton.disabled = false;
      submitButton.classList.remove("opacity-70");
    }
  });
});




/* Add film to multiple lists */
document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector("#add-to-list-page");

  if (!page || page.dataset.reevioAddToListReady === "1") return;

  page.dataset.reevioAddToListReady = "1";

  const filmId = Number(page.dataset.filmId);
  const filterInput = page.querySelector("#add-to-list-filter");
  const options = [...page.querySelectorAll("[data-list-option]")];
  const applyButton = page.querySelector("#add-to-list-apply");
  const status = page.querySelector("#add-to-list-status");

  function setStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function setOptionState(option, selected) {
    option.dataset.selected = selected ? "1" : "0";

    option.classList.toggle("bg-surface-container-high", selected);
    option.classList.toggle("border-primary/30", selected);
    option.classList.toggle("ambient-shadow", selected);
    option.classList.toggle("transform", selected);
    option.classList.toggle("hover:-translate-y-1", selected);

    option.classList.toggle("bg-surface-container-low", !selected);
    option.classList.toggle("border-outline-variant/15", !selected);
    option.classList.toggle("hover:border-outline-variant/40", !selected);

    const wrapper = option.querySelector("[data-list-icon-wrapper]");
    const icon = option.querySelector("[data-list-icon]");

    if (wrapper) {
      wrapper.className = selected
        ? "text-primary mr-2 drop-shadow-[0_0_8px_rgba(255,215,155,0.6)] transition-colors"
        : "text-surface-variant mr-2 group-hover:text-outline-variant transition-colors";
    }

    if (icon) {
      icon.textContent = selected ? "check_circle" : "radio_button_unchecked";
    }
  }

  options.forEach((option) => {
    setOptionState(option, option.dataset.selected === "1");

    option.addEventListener("click", () => {
      setOptionState(option, option.dataset.selected !== "1");
    });
  });

  filterInput?.addEventListener("input", () => {
    const query = filterInput.value.trim().toLowerCase();

    options.forEach((option) => {
      const title = option.dataset.listTitle || "";
      option.classList.toggle("hidden", query.length > 0 && !title.includes(query));
    });
  });

  applyButton?.addEventListener("click", async () => {
    const listIds = options
      .filter((option) => option.dataset.selected === "1")
      .map((option) => Number(option.dataset.listId))
      .filter(Boolean);

    if (!filmId) {
      setStatus("Film could not be resolved.", true);
      return;
    }

    applyButton.disabled = true;
    applyButton.classList.add("opacity-70");
    setStatus("Saving changes...");

    try {
      const response = await fetch("/api-lists-add-film", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({
          film_id: filmId,
          list_ids: listIds
        })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.error || payload.message || "Could not save list changes.");
      }

      setStatus("Changes saved.");
      window.location.href = payload.redirect || `/film-details?id=${filmId}`;
    } catch (error) {
      setStatus(error.message || "Could not save list changes.", true);
      applyButton.disabled = false;
      applyButton.classList.remove("opacity-70");
    }
  });
});

/* Ranked list reorder */
document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector("[data-ranked-list-reorder]");

  if (!page || page.dataset.reevioReorderReady === "1") return;

  page.dataset.reevioReorderReady = "1";

  const listId = Number(page.dataset.listId);
  const grid = page.querySelector("[data-reorder-grid]");
  const editButton = page.querySelector("[data-start-list-order]");
  const cancelButton = page.querySelector("[data-cancel-list-order]");
  const saveButton = page.querySelector("[data-save-list-order]");
  const status = page.querySelector("[data-list-order-status]");

  if (!listId || !grid || !saveButton || !editButton) return;

  let editMode = false;

  function items() {
    return [...grid.querySelectorAll("[data-reorder-item]")];
  }

  function setStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function refreshRanks() {
    items().forEach((item, index) => {
      const badge = item.querySelector("[data-rank-badge]");
      if (badge) badge.textContent = String(index + 1);

      const upButton = item.querySelector("[data-move-up]");
      const downButton = item.querySelector("[data-move-down]");

      if (upButton) upButton.disabled = !editMode || index === 0;
      if (downButton) downButton.disabled = !editMode || index === items().length - 1;
    });
  }

  function setEditMode(enabled) {
    editMode = enabled;

    page.classList.toggle("is-editing-order", editMode);
    editButton.classList.toggle("hidden", editMode);
    cancelButton?.classList.toggle("hidden", !editMode);
    saveButton.classList.toggle("hidden", !editMode);

    page.querySelectorAll("[data-reorder-controls]").forEach((controls) => {
      controls.classList.toggle("hidden", !editMode);
    });

    setStatus(editMode ? "Edit mode enabled. Use arrows, then save order." : "");
    refreshRanks();
  }

  editButton.addEventListener("click", () => {
    setEditMode(true);
  });

  cancelButton?.addEventListener("click", () => {
    window.location.reload();
  });

  page.addEventListener("click", (event) => {
    const filmOpen = event.target.closest("[data-film-open]");

    if (filmOpen && editMode) {
      event.preventDefault();
      return;
    }

    const upButton = event.target.closest("[data-move-up]");
    const downButton = event.target.closest("[data-move-down]");

    if (!upButton && !downButton) return;

    event.preventDefault();
    event.stopPropagation();

    if (!editMode) return;

    const item = event.target.closest("[data-reorder-item]");
    if (!item) return;

    if (upButton) {
      const previous = item.previousElementSibling;
      if (previous) {
        grid.insertBefore(item, previous);
        setStatus("Order changed. Save to apply.");
      }
    }

    if (downButton) {
      const next = item.nextElementSibling;
      if (next) {
        grid.insertBefore(next, item);
        setStatus("Order changed. Save to apply.");
      }
    }

    refreshRanks();
  });

  saveButton.addEventListener("click", async () => {
    const filmIds = items()
      .map((item) => Number(item.dataset.filmId))
      .filter(Boolean);

    if (!filmIds.length) {
      setStatus("Nothing to save.", true);
      return;
    }

    saveButton.disabled = true;
    saveButton.classList.add("opacity-70");
    setStatus("Saving order...");

    try {
      const response = await fetch("/api-lists-reorder", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({
          list_id: listId,
          film_ids: filmIds
        })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.error || payload.message || "Could not save order.");
      }

      setStatus("Order saved.");
      window.location.href = payload.redirect || `/list-details?id=${listId}`;
    } catch (error) {
      setStatus(error.message || "Could not save order.", true);
      saveButton.disabled = false;
      saveButton.classList.remove("opacity-70");
    }
  });

  setEditMode(false);
});

/* Remove film from list */
document.addEventListener("click", async (event) => {
  const button = event.target.closest("[data-remove-from-list]");

  if (!button) return;

  event.preventDefault();
  event.stopPropagation();

  const listId = Number(button.dataset.listId);
  const filmId = Number(button.dataset.filmId);

  if (!listId || !filmId || button.dataset.loading === "1") return;

  const confirmed = window.confirm("Remove this film from the list?");
  if (!confirmed) return;

  button.dataset.loading = "1";
  button.disabled = true;

  try {
    const response = await fetch("/api-lists-remove-film", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({
        list_id: listId,
        film_id: filmId
      })
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok || !payload.success) {
      throw new Error(payload.error || payload.message || "Could not remove film.");
    }

    window.location.href = payload.redirect || `/list-details?id=${listId}`;
  } catch (error) {
    alert(error.message || "Could not remove film.");
    button.disabled = false;
    button.dataset.loading = "0";
  }
});




/* Remove film from watchlist */
document.addEventListener("click", async (event) => {
  const button = event.target.closest("[data-remove-watchlist]");

  if (!button) return;

  event.preventDefault();
  event.stopPropagation();

  const filmId = Number(button.dataset.filmId);

  if (!filmId || button.dataset.loading === "1") return;

  const confirmed = window.confirm("Remove this film from your watchlist?");
  if (!confirmed) return;

  button.dataset.loading = "1";
  button.disabled = true;

  try {
    const response = await fetch("/api-watchlist-remove", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({ film_id: filmId })
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok || !payload.success) {
      throw new Error(payload.error || payload.message || "Could not remove film from watchlist.");
    }

    const card = button.closest("[data-watchlist-card]");
    if (card) {
      card.remove();

      const totalCounter = document.querySelector("[data-watchlist-total-count]");
      if (totalCounter) {
        const currentTotal = Number(String(totalCounter.textContent).replace(/\s/g, "")) || 0;
        const nextTotal = Math.max(0, currentTotal - 1);
        totalCounter.textContent = new Intl.NumberFormat("en-US").format(nextTotal);
      }

      if (!document.querySelector("[data-watchlist-card]")) {
        document.querySelector("[data-watchlist-empty-live]")?.classList.remove("hidden");
      }
    } else {
      window.location.href = payload.redirect || "/profile-watchlist";
    }
  } catch (error) {
    alert(error.message || "Could not remove film from watchlist.");
    button.disabled = false;
    button.dataset.loading = "0";
  }
});




/* Edit profile favorite films */
document.addEventListener("DOMContentLoaded", () => {
  const section = document.querySelector("#profile-favorites-section");

  if (!section || section.dataset.reevioFavoritesReady === "1") return;

  section.dataset.reevioFavoritesReady = "1";

  const editButton = section.querySelector("#profile-edit-favorites");
  const editor = section.querySelector("#profile-favorites-editor");
  const cancelButton = section.querySelector("#profile-cancel-favorites");
  const saveButton = section.querySelector("#profile-save-favorites");
  const countLabel = section.querySelector("#profile-favorites-count");
  const status = section.querySelector("#profile-favorites-status");
  const candidates = [...section.querySelectorAll("[data-favorite-candidate]")];

  function selectedCandidates() {
    return candidates.filter((button) => button.dataset.selected === "1");
  }

  function setStatus(message, isError = false) {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function renderCandidate(button) {
    const selected = button.dataset.selected === "1";
    button.classList.toggle("border-primary/45", selected);
    button.classList.toggle("bg-surface-container-high", selected);
    button.classList.toggle("border-outline-variant/15", !selected);
    button.classList.toggle("bg-surface-container-low", !selected);
    button.querySelector("[data-favorite-check]")?.classList.toggle("hidden", !selected);
  }

  function refreshCount() {
    const count = selectedCandidates().length;
    if (countLabel) countLabel.textContent = `${count}/4 selected`;
  }

  candidates.forEach((button) => {
    renderCandidate(button);

    button.addEventListener("click", () => {
      const selected = button.dataset.selected === "1";

      if (!selected && selectedCandidates().length >= 4) {
        setStatus("You can select up to 4 favorite films.", true);
        return;
      }

      button.dataset.selected = selected ? "0" : "1";
      renderCandidate(button);
      refreshCount();
      setStatus("");
    });
  });

  editButton?.addEventListener("click", () => {
    editor?.classList.remove("hidden");
    refreshCount();
    setStatus("");
  });

  cancelButton?.addEventListener("click", () => {
    editor?.classList.add("hidden");
    setStatus("");
  });

  saveButton?.addEventListener("click", async () => {
    const filmIds = selectedCandidates()
      .map((button) => Number(button.dataset.filmId))
      .filter(Boolean);

    if (filmIds.length > 4) {
      setStatus("You can select up to 4 favorite films.", true);
      return;
    }

    saveButton.disabled = true;
    saveButton.classList.add("opacity-70");
    setStatus("Saving favorites...");

    try {
      const response = await fetch("/api-profile-favorites", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ film_ids: filmIds })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Could not save favorites.");
      }

      setStatus("Favorites saved.");
      window.location.href = payload.redirect || "/profile";
    } catch (error) {
      setStatus(error.message || "Could not save favorites.", true);
      saveButton.disabled = false;
      saveButton.classList.remove("opacity-70");
    }
  });

  refreshCount();
});




/* Film details actions */
document.addEventListener("DOMContentLoaded", () => {
  const actions = document.querySelector("#film-details-actions");

  if (!actions || actions.dataset.reevioActionsReady === "1") return;

  actions.dataset.reevioActionsReady = "1";

  const filmId = Number(actions.dataset.filmId);
  const status = document.querySelector("#film-details-action-status");

  function setStatus(message, isError = false) {
    if (!status) return;

    status.textContent = message;
    status.classList.toggle("text-red-300", isError);
    status.classList.toggle("text-primary", !isError && message.length > 0);
    status.classList.toggle("text-on-surface-variant", message.length === 0);
  }

  function setButtonState(button, active, label, icon) {
    button.dataset.active = active ? "1" : "0";
    button.dataset.loading = "0";
    button.disabled = false;
    button.classList.remove("opacity-70");

    button.classList.toggle("bg-surface-container-high", active);
    button.classList.toggle("text-primary", active);
    button.classList.toggle("text-on-surface", !active);
    button.innerHTML = `<span class="material-symbols-outlined text-[18px]">${icon}</span>${label}`;
  }

  async function postAction(endpoint, button, loadingText) {
    if (!filmId || button.dataset.loading === "1") return;

    const originalHtml = button.innerHTML;
    button.dataset.loading = "1";
    button.disabled = true;
    button.classList.add("opacity-70");
    button.innerHTML = `<span class="material-symbols-outlined text-[18px]">hourglass_top</span>${loadingText}`;
    setStatus("");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ film_id: filmId })
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Action failed.");
      }

      setStatus(payload.message || "Done.");

      if (payload.status === "watchlisted") {
        setButtonState(button, true, "In watchlist", "bookmark_added");
        return;
      }

      if (payload.status === "unwatchlisted") {
        setButtonState(button, false, "Watchlist", "bookmark_add");
        return;
      }

      if (payload.status === "watched") {
        setButtonState(button, true, "Watched", "check_circle");

        const watchlistButton = actions.querySelector('[data-film-action="watchlist"]');
        if (watchlistButton) {
          setButtonState(watchlistButton, false, "Watchlist", "bookmark_add");
        }

        return;
      }

      if (payload.status === "unwatched") {
        setButtonState(button, false, "Mark watched", "visibility");
      }
    } catch (error) {
      setStatus(error.message || "Action failed.", true);
      button.innerHTML = originalHtml;
      button.disabled = false;
      button.classList.remove("opacity-70");
      button.dataset.loading = "0";
    }
  }

  actions.addEventListener("click", (event) => {
    const button = event.target.closest("[data-film-action]");
    if (!button) return;

    event.preventDefault();

    if (button.dataset.filmAction === "watchlist") {
      postAction("/api-watchlist-add", button, button.dataset.active === "1" ? "Removing..." : "Adding...");
      return;
    }

    if (button.dataset.filmAction === "watched") {
      postAction("/api-film-mark-watched", button, button.dataset.active === "1" ? "Removing..." : "Saving...");
    }
  });
});




/* Review interactions */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".share-current-url").forEach((button) => {
    if (button.dataset.reevioShareReady === "1") return;
    button.dataset.reevioShareReady = "1";

    button.addEventListener("click", async (event) => {
      event.preventDefault();
      event.stopPropagation();

      try {
        const shareUrl = button.dataset.shareUrl
          ? new URL(button.dataset.shareUrl, window.location.origin).href
          : window.location.href;

        await navigator.clipboard.writeText(shareUrl);
        const previous = button.innerHTML;
        button.innerHTML = '<span class="material-symbols-outlined">check</span>';
        setTimeout(() => {
          button.innerHTML = previous;
        }, 1200);
      } catch {
        window.prompt("Copy this URL", button.dataset.shareUrl ? new URL(button.dataset.shareUrl, window.location.origin).href : window.location.href);
      }
    });
  });

  document.querySelectorAll("[data-review-like]").forEach((button) => {
    if (button.dataset.reevioLikeReady === "1") return;
    button.dataset.reevioLikeReady = "1";

    button.addEventListener("click", async (event) => {
      event.preventDefault();

      const reviewId = Number(button.dataset.reviewId);
      if (!reviewId || button.dataset.loading === "1") return;

      button.dataset.loading = "1";

      try {
        const response = await fetch("/api-reviews-like", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ review_id: reviewId })
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Could not update like.");
        }

        button.dataset.liked = payload.liked ? "1" : "0";
        button.classList.toggle("text-primary", Boolean(payload.liked));
        button.classList.toggle("text-on-surface-variant", !payload.liked);
        const count = button.querySelector("[data-review-like-count]");
        if (count) count.textContent = String(payload.likes_count ?? 0);
      } finally {
        button.dataset.loading = "0";
      }
    });
  });

  document.querySelectorAll("[data-comment-like]").forEach((button) => {
    if (button.dataset.reevioCommentLikeReady === "1") return;
    button.dataset.reevioCommentLikeReady = "1";

    button.addEventListener("click", async (event) => {
      event.preventDefault();

      const commentId = Number(button.dataset.commentId);
      if (!commentId || button.dataset.loading === "1") return;

      button.dataset.loading = "1";

      try {
        const response = await fetch("/api-review-comments-like", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ comment_id: commentId })
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Could not update comment like.");
        }

        button.dataset.liked = payload.liked ? "1" : "0";
        button.classList.toggle("text-primary", Boolean(payload.liked));
        button.classList.toggle("text-on-surface-variant", !payload.liked);
        const count = button.querySelector("[data-comment-like-count]");
        if (count) count.textContent = String(payload.likes_count ?? 0);
      } finally {
        button.dataset.loading = "0";
      }
    });
  });

  document.querySelectorAll("[data-review-comment-form], [data-reply-form]").forEach((form) => {
    if (form.dataset.reevioCommentFormReady === "1") return;
    form.dataset.reevioCommentFormReady = "1";

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const reviewId = Number(form.dataset.reviewId);
      const parentId = Number(form.dataset.parentId || 0);
      const textarea = form.querySelector('textarea[name="content"]');
      const status = form.querySelector("[data-comment-status]");
      const content = textarea ? textarea.value.trim() : "";

      if (!reviewId || !content) {
        if (status) status.textContent = "Write a comment first.";
        return;
      }

      const submit = form.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;

      try {
        const response = await fetch("/api-reviews-comment", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({
            review_id: reviewId,
            parent_id: parentId || null,
            content
          })
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Could not post comment.");
        }

        window.location.reload();
      } catch (error) {
        if (status) status.textContent = error.message || "Could not post comment.";
        if (submit) submit.disabled = false;
      }
    });
  });

  document.querySelectorAll("[data-reply-open]").forEach((button) => {
    button.addEventListener("click", () => {
      const commentId = button.dataset.commentId;
      const form = document.querySelector(`[data-reply-form][data-parent-id="${commentId}"]`);
      if (form) form.classList.remove("hidden");
    });
  });

  document.querySelectorAll("[data-reply-cancel]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = button.closest("[data-reply-form]");
      if (form) form.classList.add("hidden");
    });
  });
});



/* Profile list delete */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-delete-list]").forEach((button) => {
    if (button.dataset.reevioDeleteReady === "1") return;
    button.dataset.reevioDeleteReady = "1";

    button.addEventListener("click", async (event) => {
      event.preventDefault();
      event.stopPropagation();

      const listId = Number(button.dataset.listId);
      if (!listId || button.dataset.loading === "1") return;

      const confirmed = window.confirm("Delete this list?");
      if (!confirmed) return;

      const previous = button.innerHTML;
      button.dataset.loading = "1";
      button.disabled = true;
      button.innerHTML = '<span class="material-symbols-outlined text-[15px]">hourglass_top</span>Deleting';

      try {
        const response = await fetch("/api-lists-delete", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ list_id: listId })
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
          throw new Error(payload.error || payload.message || "Could not delete list.");
        }

        const card = button.closest("[data-profile-list-card]");
        if (card) card.remove();

        const total = document.querySelector("[data-profile-lists-total]");
        if (total) {
          const current = Number(String(total.textContent || "0").replace(/\s/g, "")) || 0;
          total.textContent = String(Math.max(0, current - 1));
        }
      } catch (error) {
        alert(error.message || "Could not delete list.");
        button.dataset.loading = "0";
        button.disabled = false;
        button.innerHTML = previous;
      }
    });
  });
});




/* Offline support */
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-offline-retry]").forEach((button) => {
    if (button.dataset.reevioRetryReady === "1") return;
    button.dataset.reevioRetryReady = "1";

    button.addEventListener("click", () => {
      window.location.reload();
    });
  });
});

if ("serviceWorker" in navigator && window.location.protocol !== "file:") {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("/service-worker.js").catch(() => {
      // Offline fallback is progressive enhancement; app should still work without it.
    });
  });
}

