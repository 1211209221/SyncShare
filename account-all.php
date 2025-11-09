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
pre { background: #04a3ce; color: white; padding: 0px 25px; border-radius: 0px; margin: 0px;font-family: 'Lato', sans-serif;}
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
  background: #f3f4f6;
  border-radius: 12px;
  padding: 15px;
  
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

.actions button{
    display: inline-block;
    width: auto;
    margin-right: 5px;
    background: #28a745;
    transition: transform 0.15s ease-in-out;
}

.modal-button{
    width: auto;
    margin-right: 5px;
    background: #28a745;
    color: white;
    transition: transform 0.15s ease-in-out;
}

.actions button.delete { background: #dc3545; }
.actions button.update { background: #ffc107; color: #000; }
.ui-container{
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #888;
}

.ui-container{
    height: 630px;
}

.actions i:hover{
    transform: scale(1.1);
    color:#04a3ce;
    cursor: pointer;
}

.modal-button{
    height: 50px !important;
    margin: 0;
    font-size: 18px;
    transition: 0.15s;
}

.modal-button i{
    margin-right: 5px;
}

.modal-button:hover {
    transform: scale(1.05);
    cursor: pointer;
    color: white !important;
    background-color:#1b78aeff !important;
}


.container{
    padding: 0px 1.5rem!important
}

input:focus,
select:focus,
button:focus {
    outline: none !important;
    box-shadow: none !important;
}

input, select, button, span{
    border: none !important;
}

#accountSearch, #accountFilter, #accountSearch::placeholder{
    font-size: 17px;
    color: #888;
    margin: 0;
}

#accountSearch{
    width: 50% !important;
}

#accountFilter{
    width: 25% !important;
}

.add-account{
    width: 25% !important;
    margin: 0;
}

button.add-account{
    background: #04a3ce !important;
    color: white;
    cursor: pointer;
    transition: 0.15s ease-in-out;
}

button.add-account:hover {
    background: #1b78aeff !important;
    transform: scale(1.05);
}

.modal-header{
    background-color: #04a3ce !important;
    justify-content: space-between;
    padding-top: 0px;
    padding-bottom: 0px;
}

.modal-close-btn{
    width: 10%;
}

.modal-close-btn i{
    transform: scale(1.3);
    cursor: pointer;
    transition: 0.15s;
}

.modal-close-btn:hover i{
    transform: scale(1.4);
    cursor: pointer;
}

.modal-title, .fa-times{
    color: white;
}

#renameAccountInput{
    background:#f3f4f6;
    font-size: 18px;
}

.modal-body p{
    font-size: 16px;
    margin-bottom: 2px;
    text-align: left !important;
}

#renameAccountModal button, #deleteAccountModal button{
    background: #04a3ce !important;
    height: 40px;
    font-size: 18px;
    transition: 0.15s;
}
#renameAccountModal button:hover, #deleteAccountModal button:hover{
    background: #1b78aeff !important;
    transform: scale(1.05);
}

#renameAccountModal button i, #deleteAccountModal button i{
    padding-right: 5px;
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
                <div class="py-4 px-4">
                    <h1>Accounts</h1>
                </div>
                <div class="d-flex">
                    <div class="dashboard container container-fluid" id="dashboard">
                        <!-- Search & Filter -->
                        <div class="d-flex gap-2 mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search" style="color: #888;"></i></span>
                                <input type="text" id="accountSearch" class="form-control border-start-0" placeholder="Search by account name...">
                            </div>
                            <select id="accountFilter" class="form-select">
                                <option value="">All Platforms</option>
                                <option value="instagram">Instagram</option>
                                <option value="twitter">Twitter</option>
                                <option value="facebook">Facebook</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="mastodon">Mastodon</option>
                            </select>
                            <!-- Add Account Button -->
                            <button class="add-account btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                                <i class="fas fa-plus"></i><b>  New Account </b>
                            </button>
                        </div>
                        <div class="ui-container">
                        <h2>Your Connected Accounts</h2>


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
                                    <button type="button" class="btn modal-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color:white; background:transparent; border:none; font-size:1.2rem;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body text-center p-3">
                                    <p style="width:100%; text-align: start; font-size: 18px;">Select a platform to connect:</p>
                                    <div class="d-grid gap-3">
                                    <button class="btn btn-outline-danger modal-button" onclick="connectProvider('instagram')">
                                        <i class="fab fa-instagram"></i> Instagram
                                    </button>
                                    <button class="btn btn-outline-info  modal-button" onclick="connectProvider('twitter')">
                                        <i class="fab fa-twitter"></i> Twitter
                                    </button>
                                    <button class="btn btn-outline-primary  modal-button" onclick="connectProvider('facebook')">
                                        <i class="fab fa-facebook"></i> Facebook
                                    </button>
                                    <button class="btn btn-outline-secondary  modal-button" onclick="connectProvider('linkedin')">
                                        <i class="fab fa-linkedin"></i> LinkedIn
                                    </button>
                                    <button class="btn btn-outline-dark  modal-button" onclick="connectProvider('mastodon')">
                                        <i class="fab fa-mastodon"></i> Mastodon
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 🗑️ Delete Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-trash"></i> Delete Account</h5>
        <button type="button" class="btn modal-close-btn" data-bs-dismiss="modal" aria-label="Close" style="color:white; background:transparent; border:none; font-size:1.2rem;">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body text-center p-3">
        <p class="fs-5">Are you sure you want to delete <b id="deleteAccountName"></b>?</p>
        <p class="text-muted">This action cannot be undone.</p>
        <div class="d-flex justify-content-center gap-3 mt-4">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmDeleteBtn" class="btn btn-danger px-4">
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ✏️ Rename Account Modal -->
<div class="modal fade" id="renameAccountModal" tabindex="-1" aria-labelledby="renameAccountLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-edit"></i> Rename Account</h5>
        <button type="button" class="btn modal-close-btn" data-bs-dismiss="modal" aria-label="Close" style="background:transparent; border:none; font-size:1.2rem;">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body text-center">
        <p class="fs-5 mb-3">Enter a new name for <b id="renameAccountCurrentName"></b>:</p>
        <input type="text" id="renameAccountInput" class="form-control mb-3" placeholder="New account name">
        <div class="d-flex justify-content-center gap-3">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmRenameBtn" class="btn btn-primary px-4">
            <i class="fas fa-check"></i> Save
          </button>
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
        accountsDiv.innerHTML = " Loading accounts...";

        const res = await api('fetch');
        allAccounts = Array.isArray(res) ? res : res.items || [];

        if (allAccounts.length === 0) {
            accountsDiv.innerHTML = "⚠️ No accounts found or error fetching.";
            out.textContent = JSON.stringify(res, null, 2);
            return;
        }

        renderAccounts(allAccounts); // render all accounts initially
    }

    function getAccountType(acc) {
        // Normalize types so dropdown filter works
        const type = (acc._type || acc.type || '').toLowerCase();
        if (type.includes('mastodon')) return 'mastodon';
        if (type.includes('twitter') || type.includes('x')) return 'twitter';
        if (type.includes('facebook')) return 'facebook';
        if (type.includes('instagram')) return 'instagram';
        if (type.includes('linkedin')) return 'linkedin';
        return 'unknown';
    }


    function renderAccounts(accounts) {
    const accountsDiv = document.getElementById('accounts');
    accountsDiv.innerHTML = "";

    if (!accounts || accounts.length === 0) {
        accountsDiv.innerHTML = `
            <div class="text-center text-muted" style="grid-column: 1 / -1; padding: 30px;">
                <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                <strong>No accounts found.</strong>
            </div>
        `;
        return;
    }

    accounts.forEach(acc => {
        const type = getAccountType(acc);
        const div = document.createElement('div');
        div.className = "account";
        div.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <img src="${acc.image || 'https://socialbu.com/images/no-image.png'}" 
                    alt="Profile Picture" width="50" height="50" 
                    style="border-radius:50%; border:1px solid #ccc;">
                <div>
                    <strong>${acc.name || "(Unnamed Account)"}</strong><br>
                    <small>${acc._type || acc.type || "Unknown Platform"}</small><br>
                    <small>Status: ${acc.active 
                        ? '<span style="color:green;"><i class="fas fa-link"></i> Linked</span>' 
                        : '<span style="color:red;"><i class="fas fa-unlink"></i> Unlinked</span>'}</small>
                </div>
            </div>
            <div class="actions" style="margin-top:6px; display:flex; gap:10px; font-size:18px;">
                <i class="fas fa-edit update" title="Rename" onclick="renameAccount(${acc.id})"></i>
                <i class="fas fa-trash delete" title="Delete" onclick="deleteAccount(${acc.id})"></i>
            </div>
        `;
        accountsDiv.appendChild(div);
    });
}



    document.getElementById('accountSearch').addEventListener('input', filterAccounts);
    document.getElementById('accountFilter').addEventListener('change', filterAccounts);

    function filterAccounts() {
        const searchTerm = document.getElementById('accountSearch').value.toLowerCase();
        const selectedType = document.getElementById('accountFilter').value.toLowerCase();

        const filtered = allAccounts.filter(acc => {
            const type = getAccountType(acc);
            const matchesName = acc.name.toLowerCase().includes(searchTerm);
            const matchesType = selectedType ? type === selectedType : true;
            return matchesName && matchesType;
        });

        renderAccounts(filtered);
    }



let selectedAccountId = null;
let selectedAccountName = null;

// 🗑️ Trigger delete modal
function deleteAccount(id) {
  const acc = allAccounts.find(a => a.id === id);
  if (!acc) return;

  selectedAccountId = id;
  selectedAccountName = acc.name || "(Unnamed Account)";
  document.getElementById('deleteAccountName').textContent = selectedAccountName;

  const deleteModal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));
  deleteModal.show();
}

// Confirm delete
document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
  const out = document.getElementById('output');
  out.innerHTML = `<i class="fas fa-trash text-danger"></i> Deleting ${selectedAccountName}...`;

  const res = await api('delete', { accountId: selectedAccountId });
  const modal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
  modal.hide();

  if (res.success) {
    out.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${res.message || "Account deleted successfully."}`;
  } else {
    out.innerHTML = `<i class="fas fa-times-circle text-danger"></i> ${res.message || "Failed to delete account."}`;
  }

  loadAccounts();
});


// ✏️ Trigger rename modal
function renameAccount(id) {
  const acc = allAccounts.find(a => a.id === id);
  if (!acc) return;

  selectedAccountId = id;
  selectedAccountName = acc.name || "(Unnamed Account)";
  document.getElementById('renameAccountCurrentName').textContent = selectedAccountName;
  document.getElementById('renameAccountInput').value = selectedAccountName;

  const renameModal = new bootstrap.Modal(document.getElementById('renameAccountModal'));
  renameModal.show();
}

// Confirm rename
document.getElementById('confirmRenameBtn').addEventListener('click', async () => {
  const newName = document.getElementById('renameAccountInput').value.trim();
  if (!newName) return;

  const out = document.getElementById('output');
  out.innerHTML = `<i class="fas fa-edit text-primary"></i> Renaming account...`;

  const res = await api('update', { accountId: selectedAccountId, name: newName });
  const modal = bootstrap.Modal.getInstance(document.getElementById('renameAccountModal'));
  modal.hide();

  if (res.success) {
    out.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${res.message || "Account renamed successfully."}`;
  } else {
    out.innerHTML = `<i class="fas fa-times-circle text-danger"></i> ${res.message || "Failed to rename account."}`;
  }

  loadAccounts();
});


async function connectProvider(provider) {
    const out = document.getElementById('output');
    out.innerHTML = `<i class="fas fa-sync-alt fa-spin text-info"></i> Connecting to ${provider}...`;

    const res = await api('add', { provider });

    if (res.connect_url) {
        out.innerHTML = `<i class="fas fa-check-circle text-success"></i> Got connect URL for ${provider}. Account successfully added.`;
        window.open(res.connect_url, "_blank", "width=600,height=700");

        const modal = bootstrap.Modal.getInstance(document.getElementById('addAccountModal'));
        modal.hide();

        setTimeout(loadAccounts, 10000);
    } else {
        out.innerHTML = `<i class="fas fa-times-circle text-danger"></i> Failed to get connect URL: ${res.message || 'Unknown error'}`;
    }
}


    window.onload = loadAccounts;
    </script>
</html>
