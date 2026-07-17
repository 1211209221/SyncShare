# SyncShare

> AI-Powered Multi-Platform Social Media Publishing Tool

SyncShare is a web-based application that enables users to manage multiple social media accounts from a single platform. It provides AI-assisted content generation, image generation, analytics insights, post scheduling, and multi-platform publishing through integration with external AI services and the SocialBu API.

This project was developed as a Final Year Project (FYP) for the Bachelor of Computer Science (Hons) in Artificial Intelligence at Multimedia University.

---

## Features

- Multi-platform social media publishing
- AI-assisted content generation
- AI post rewriting
- AI image generation
- AI-powered analytics insights
- Social media account management
- Post scheduling
- Content calendar
- Account analytics dashboard
- Engagement tracking
- AI chat assistant

---

## Technologies Used

### Backend

- PHP 8.1+
- Apache (XAMPP recommended)

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- Chart.js

### APIs

- Google Gemini API
- Groq API
- Pixazo AI
- SocialBu API

---

## Requirements

- PHP 8.1 or higher
- Apache Web Server
- XAMPP (recommended)
- Internet connection

Required PHP extensions:

- cURL
- JSON
- MySQLi / PDO

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/<username>/SyncShare.git

---

### 2. Place the project inside XAMPP

```
C:\xampp\htdocs\SyncShare
```

---

### 3. Start Apache

Open the XAMPP Control Panel and start:

- Apache

---

### 4. Configure API Keys
This project depends on the following third-party services:

- Google Gemini API
- Groq API
- Pixazo AI
- SocialBu API

Valid API credentials are required to use AI generation and publishing features. Generate your own API credentials before running the project

Example configuration format:
$API_KEY = "YOUR_GEMINI_API_KEY";

---

### 5. Run the application

Open your browser and navigate to:

```
http://localhost/SyncShare
```

Register or log in using your SocialBu account.

---

## Project Structure

```
SyncShare/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── uploads/
├── cache/
│
├── dashboard.php
├── post-new.php
├── post-view.php
├── post-calendar.php
├── account-analytics.php
│
├── ai-content-studio.php
├── ai-chat.php
├── ai-analysis-chat.php
├── ai-generate-image.php
├── ai-rewrite.php
│
├── login.php
├── logout.php
├── index.php
│
├── config.example.php
├── .gitignore
└── README.md
```

---

## Architecture

SyncShare follows a modular client-server architecture consisting of:

- User Interface Layer
- Backend Business Logic
- AI Service Layer
- Social Media Integration Layer

The application communicates with external APIs for AI generation, analytics retrieval, image generation, and social media publishing.

---

## Known Limitations

- Dependent on external APIs
- API rate limits may affect performance
- AI-generated content may require manual editing
- Analytics are limited to the data provided by connected platforms

---

## Documentation

Detailed technical documentation is available in:

- Developer Manual
- Final Year Project Report

---

## License

This project was developed for academic purposes as part of a Final Year Project at Multimedia University.

Please respect the licenses and terms of service of all integrated third-party APIs.
