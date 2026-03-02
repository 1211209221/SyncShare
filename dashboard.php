<?php
session_start();

// --- PHP Backend Section --- //
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    // FETCH ACCOUNTS
    if (isset($_SESSION['token']) && $input['action'] === 'fetch') {
        $ch = curl_init('https://socialbu.com/api/v1/accounts');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $_SESSION['token'],
                'Content-Type: application/json'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        exit;
    }

    // ADD ACCOUNT
    if (isset($_SESSION['token'], $input['provider']) && $input['action'] === 'add') {
        $ch = curl_init('https://socialbu.com/api/v1/accounts');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $_SESSION['token'],
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['provider' => $input['provider']])
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        exit;
    }

    // DELETE ACCOUNT
    if (isset($_SESSION['token'], $input['accountId']) && $input['action'] === 'delete') {
        $url = 'https://socialbu.com/api/v1/accounts/' . $input['accountId'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $_SESSION['token'],
                'Content-Type: application/json'
            ]
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        exit;
    }

    // UPDATE ACCOUNT
    if (isset($_SESSION['token'], $input['accountId'], $input['name']) && $input['action'] === 'update') {
        $url = 'https://socialbu.com/api/v1/accounts/' . $input['accountId'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $_SESSION['token'],
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['name' => $input['name']])
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
        exit;
    }

    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Redirect if not logged in
if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SocialBu Account Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f8;
    color: #333;
    margin: 0;
}
h1 {
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 0px;
}
.dashboard {
    
}
button, select {
    display: block;
    width: 100%;
    margin: 10px 0;
    padding: 10px;
    font-size: 16px;
}
button {
    background: #007bff;
    border: none;
    color: white;
    border-radius: 5px;
    cursor: pointer;
}
button:hover { background: #0056b3; }
pre { background: #04a3ce; color: white; padding: 0px; border-radius: 0px; margin: 0px;}
#accounts {
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* exactly 3 per row */
  gap: 20px;
}

@media (max-width: 1024px) {
  #accounts {
    grid-template-columns: repeat(2, 1fr); /* 2 per row on smaller screens */
  }
}

@media (max-width: 768px) {
  #accounts {
    grid-template-columns: 1fr; /* 1 per row on mobile */
  }
}


.account {
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 12px;
  padding: 15px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  justify-content: space-between;
}

.account img{
    width: 60px;
    height: 60px;
}

.actions{
    flex-direction: column;
}

.actions button {
    display: inline-block;
    width: auto;
    margin-right: 5px;
    background: #28a745;
    transition: transform 0.15s ease-in-out;
}
.actions button.delete { background: #dc3545; }
.actions button.update { background: #ffc107; color: #000; }
.ui-container{
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    color: #9a97a7;
}

.actions i:hover {
    transform: scale(1.1);
    color:#04a3ce;
    cursor: pointer;
}

.container{
    padding: 0px 1.5rem!important
}
</style>
</head>
<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php
                include 'sidebar.php';
            ?>
            <div style="width:100%;">
                <pre id="output"></pre>
                <div class="py-3 px-3" style="background-color: white; margin-bottom: 20px;">
                    <h1>Dashboard</h1>
                </div>
                <div class="d-flex">
  <div class="dashboard container container-fluid" id="dashboard">
    <div class="ui-container">
      <h2>Your Connected Accounts</h2>

      <!-- Add Account Button -->
      <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addAccountModal">
        ➕ Add Account
      </button>

      <!-- Accounts Grid -->
      <div id="accounts" class="mt-4"></div>
    </div>
  </div>
</div>

<!-- 🌟 Floating Modal Window -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0 rounded-4">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addAccountModalLabel">Connect a New Account</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <p>Select a platform to connect:</p>
        <div class="d-grid gap-3">
          <div class="d-grid gap-3">
            <button class="btn btn-outline-danger" onclick="connectProvider('instagram')">📸 Instagram</button>
            <button class="btn btn-outline-info" onclick="connectProvider('twitter')">🐦 Twitter</button>
            <button class="btn btn-outline-primary" onclick="connectProvider('facebook')">📘 Facebook</button>
            <button class="btn btn-outline-secondary" onclick="connectProvider('linkedin')">💼 LinkedIn</button>
            <button class="btn btn-outline-dark" onclick="connectProvider('mastodon')">🦣 Mastodon</button>
        </div>
      </div>
    </div>
  </div>
</div>

            </div>
        </div>
    </div>
</body>
<script>
    async function api(action, body = {}) {
            const res = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "fetch"
                },
                body: JSON.stringify({ ...body, action })
            });
            const text = await res.text();
            try { return JSON.parse(text); } catch { return { raw: text }; }
        }

        async function loadAccounts() {
        const out = document.getElementById('output');
        const accountsDiv = document.getElementById('accounts');
        accountsDiv.innerHTML = "🔄 Loading accounts...";

        const res = await api('fetch');
        // The API returns an array, not { items: [...] }
        const accounts = Array.isArray(res) ? res : res.items || [];

        if (accounts.length === 0) {
            accountsDiv.innerHTML = "⚠️ No accounts found or error fetching.";
            out.textContent = JSON.stringify(res, null, 2);
            return;
        }

        accountsDiv.innerHTML = "";

        accounts.forEach(acc => {
            // Function to create the account div
            const createAccountDiv = (acc) => {
                const div = document.createElement('div');
                div.className = "account";
                div.innerHTML = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="${acc.image || 'https://socialbu.com/images/no-image.png'}" 
                            alt="Profile Picture" 
                            width="50" height="50" 
                            style="border-radius:50%; border:1px solid #ccc;">
                        <div>
                            <strong>${acc.name || "(Unnamed Account)"}</strong><br>
                            <small>${acc._type || acc.type || "Unknown Platform"}</small><br>
                            <small>Status: ${acc.active ? '<i class="fas fa-link" style="color:green;"></i> Linked' : '<i class="fas fa-unlink" style="color:red;"></i> Unlinked'}</small>

                        </div>
                    </div>
                    <div class="actions" style="margin-top:6px; display:flex; gap:10px; font-size:18px;">
                        <i class="fas fa-edit update" cursor:pointer;" title="Rename" onclick="renameAccount(${acc.id})"></i>
                        <i class="fas fa-trash delete" cursor:pointer;" title="Delete" onclick="deleteAccount(${acc.id})"></i>
                    </div>
                `;
                return div;
            };

            // Append the account **twice**
            accountsDiv.appendChild(createAccountDiv(acc));
        });
    }


    async function deleteAccount(id) {
        if (!confirm("Are you sure you want to delete this account?")) return;
        const out = document.getElementById('output');
        out.textContent = "🗑️ Deleting account " + id + "...";
        const res = await api('delete', { accountId: id });
        out.textContent = JSON.stringify(res, null, 2);
        loadAccounts();
    }

    async function renameAccount(id) {
        const name = prompt("Enter new name for account:");
        if (!name) return;
        const out = document.getElementById('output');
        out.textContent = "✏️ Renaming account...";
        const res = await api('update', { accountId: id, name });
        out.textContent = JSON.stringify(res, null, 2);
        loadAccounts();
    }

async function connectProvider(provider) {
  const out = document.getElementById('output');
  out.textContent = `🔄 Connecting to ${provider}...`;

  const res = await api('add', { provider });

  if (res.connect_url) {
    out.textContent = `Got connect URL for ${provider}. Opening popup...`;
    window.open(res.connect_url, "_blank", "width=600,height=700");
    const modal = bootstrap.Modal.getInstance(document.getElementById('addAccountModal'));
    modal.hide(); // Close modal
    setTimeout(loadAccounts, 10000);
  } else {
    out.textContent = `Failed to get connect URL:\n${JSON.stringify(res, null, 2)}`;
  }
}


    window.onload = loadAccounts;
    </script>
</html>
