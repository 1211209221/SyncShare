<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsplash Image Selector</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Masonry layout for Unsplash results */
#results {
    column-count: 4;
    column-gap: 15px;
}

.image-result {
    width: 100%;
    display: block;
    margin-bottom: 15px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.2s;
}

.image-result:hover {
    transform: scale(1.05);
}

.image-result.selected {
    border: 5px solid #0d6efd;
}

/* Preview thumbnails */
.preview-thumb {
    width: 100px;
    border-radius: 10px;
}
</style>
</head>
<body class="bg-light">

<div class="container mt-5 bg-white p-4 rounded shadow">
    <h3>Unsplash Image Selector</h3>

    <!-- Hidden input to store selected images -->
    <input type="hidden" id="library_images" name="library_images">

    <!-- Preview Selected Images -->
    <div id="previewContainer" class="d-flex gap-2 flex-wrap mb-3"></div>

    <!-- Button to open Unsplash modal -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unsplashModal">
        Browse Image Library
    </button>
</div>

<!-- Unsplash Modal -->
<div class="modal fade" id="unsplashModal">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Select up to 4 images</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="search" class="form-control" placeholder="Search images">
                    <button class="btn btn-primary" onclick="searchImages(true)">Search</button>
                </div>

                <div id="results"></div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="confirmSelection()" data-bs-dismiss="modal">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const UNSPLASH_KEY = "ss5ZA_F9VdyaTWXOm6v3k3lWPvXOpAfftC6k-2ESEHE";
let selected = [];
let currentPage = 1;

/* ===============================
   Search Images from Unsplash
================================ */
async function searchImages(reset = false, page = 1) {
    if (reset) currentPage = 1;

    let query = document.getElementById("search").value || "nature";

    let res = await fetch(
        `https://api.unsplash.com/search/photos?per_page=30&page=${page}&query=${query}&client_id=${UNSPLASH_KEY}`
    );

    let data = await res.json();
    let html = "";

    if (data.results && data.results.length > 0) {
        data.results.forEach(img => {
            html += `
                <img src="${img.urls.small}" 
                     class="image-result" 
                     onclick="toggleSelect('${img.urls.full}', this)">
            `;
        });
    } else {
        html = `<p class="text-center text-muted mt-3">No results found.</p>`;
    }

    if (page === 1) {
        document.getElementById("results").innerHTML = html;
    } else {
        document.getElementById("results").innerHTML += html;
    }

    // Load More button
    if (data.total && currentPage * 30 < data.total) {
        if (!document.getElementById("loadMoreBtn")) {
            let btn = document.createElement("button");
            btn.id = "loadMoreBtn";
            btn.className = "btn btn-secondary w-100 mt-2";
            btn.textContent = "Load More";
            btn.onclick = () => {
                currentPage++;
                searchImages(false, currentPage);
            };
            document.getElementById("results").after(btn);
        }
    } else {
        let btn = document.getElementById("loadMoreBtn");
        if (btn) btn.remove();
    }
}

/* ===============================
   Toggle Image Selection
================================ */
function toggleSelect(url, el) {
    if (selected.includes(url)) {
        selected = selected.filter(i => i !== url);
        el.classList.remove("selected");
    } else {
        if (selected.length >= 4) {
            alert("Maximum 4 images");
            return;
        }
        selected.push(url);
        el.classList.add("selected");
    }
}

/* ===============================
   Confirm Selection & Preview
================================ */
function confirmSelection() {
    document.getElementById("library_images").value = JSON.stringify(selected);

    let html = "";
    selected.forEach(url => {
        html += `<img src="${url}" class="preview-thumb">`;
    });

    document.getElementById("previewContainer").innerHTML = html;
}

/* ===============================
   Auto Load When Modal Opens
================================ */
document.getElementById('unsplashModal').addEventListener('shown.bs.modal', () => {
    if (!document.getElementById("results").innerHTML) {
        currentPage = 1;
        searchImages();
    }
});
</script>
</body>
</html>