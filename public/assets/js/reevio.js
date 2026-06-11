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
      films: "/search-films",
      actors: "/search-actors",
      users: "search_users.html",
      lists: "search_lists.html"
    };

    let selectedTarget = "/search-films";

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

