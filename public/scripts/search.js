const search = document.querySelector('input[placeholder="search project"]');
const usersContainer = document.querySelector(".users-list");

usersContainer.addEventListener("click", function (event) {
  const deleteButton = event.target.closest(".delete-user-button");

  if (!deleteButton) {
    return;
  }

  const userId = deleteButton.dataset.userId;

  fetch("/delete-user", {
    method: "DELETE",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: userId }),
  })
    .then(function (response) {
      if (!response.ok) {
        throw new Error("Nie udało się usunąć użytkownika.");
      }

      return response.json();
    })
    .then(function () {
      const userRow = deleteButton.closest(".user-row");

      if (userRow) {
        userRow.remove();
      }
    })
    .catch(function (error) {
      console.error(error);
    });
});

search.addEventListener("keyup", function (event) {
  if (event.key === "Enter") {
    event.preventDefault();
    const query = search.value.trim();

    console.log("Search query:", query);

    const data = { search: this.value };

    fetch("/search", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (users) {
        usersContainer.innerHTML = "";
        loadUsers(users);
      });
  }
});

function loadUsers(users) {
    // html template
  users.forEach(function (user) {
    const userElement = document.createElement("article");
    userElement.classList.add("user-row");
    userElement.dataset.userId = user.id;

    const avatar = document.createElement("span");
    avatar.textContent = user.full_name.charAt(0).toUpperCase();
    avatar.classList.add("user-avatar");

    const userDiv = document.createElement("div");

    const username = document.createElement("h2");
    username.textContent = user.username;

    const fullname = document.createElement("p");
    fullname.textContent = user.full_name;

    userDiv.appendChild(username);
    userDiv.appendChild(fullname);

    const deleteButton = document.createElement("button");
    deleteButton.classList.add("delete-user-button");
    deleteButton.type = "button";
    deleteButton.dataset.userId = user.id;
    deleteButton.setAttribute("aria-label", "Usuń użytkownika " + user.username);
    deleteButton.title = "Usuń użytkownika";
    deleteButton.innerHTML = `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 6h18" />
        <path d="M8 6V4h8v2" />
        <path d="M6 6l1 15h10l1-15" />
        <path d="M10 11v6" />
        <path d="M14 11v6" />
      </svg>
    `;

    userElement.appendChild(avatar);
    userElement.appendChild(userDiv);
    userElement.appendChild(deleteButton);
    usersContainer.appendChild(userElement);
  });
}
