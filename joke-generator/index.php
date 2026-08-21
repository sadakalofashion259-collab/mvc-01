<?php
declare(strict_types=1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Session timeout
$timeout_duration = 1800;
$last_activity = $_SESSION['LAST_ACTIVITY'] ?? null;
if ($last_activity !== null && is_int($last_activity) && (time() - $last_activity) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check if logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$userRole = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Joke Generator - SADA KALO</title>
    <meta name="theme-color" content="#ffffff">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-light: #e0e7ff;
            --success: #10b981;
            --danger: #ef4444;
            --warn: #f59e0b;
            --bg: #f3f4f6;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text-muted: #6b7280;
        }

        [data-theme="dark"] {
            --bg: #1f2937;
            --surface: #111827;
            --border: #374151;
            --text: #f3f4f6;
            --text-muted: #9ca3af;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            color: white;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-right {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .container-main {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .card-joke {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .card-joke:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .joke-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .joke-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .joke-category {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary) 0%, #8b5cf6 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary-custom {
            background: var(--surface);
            color: var(--text);
            border: 2px solid var(--border);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary-custom:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .loading {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            border-left: 4px solid #dc2626;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
            border-left: 4px solid #16a34a;
        }

        .history-list {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .history-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .filter-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .filter-btn {
            padding: 0.625rem 1rem;
            border: 2px solid var(--border);
            background: var(--surface);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 3rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
            }

            .header h1 {
                font-size: 1.25rem;
            }

            .joke-text {
                font-size: 1.25rem;
            }

            .controls {
                flex-direction: column;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                width: 100%;
                justify-content: center;
            }
        }

        /* Dark mode toggle */
        .theme-toggle {
            position: relative;
        }

        .joke-loader {
            text-align: center;
        }

        .joke-loader p {
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        <i class="fas fa-laugh-beam"></i>
        Joke Generator
    </h1>
    <div class="header-right">
        <span class="user-badge">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($username); ?>
        </span>
        <button class="btn-icon theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
            <i class="fas fa-moon"></i>
        </button>
        <a href="../logout.php" class="btn-icon" title="Logout">
            <i class="fas fa-power-off"></i>
        </a>
    </div>
</div>

<div class="container-main">
    <div class="stats" id="statsContainer"></div>

    <div class="error-message" id="errorMessage"></div>
    <div class="success-message" id="successMessage"></div>

    <div class="filter-options">
        <button class="filter-btn active" onclick="filterJokes('all')">All Categories</button>
        <button class="filter-btn" onclick="filterJokes('general')">General</button>
        <button class="filter-btn" onclick="filterJokes('programming')">Programming</button>
        <button class="filter-btn" onclick="filterJokes('knock-knock')">Knock-Knock</button>
    </div>

    <div class="card-joke" id="jokeCard">
        <div class="joke-loader">
            <div class="loading"></div>
            <p>Loading a funny joke...</p>
        </div>
    </div>

    <div class="controls">
        <button class="btn-primary-custom" onclick="getRandomJoke()">
            <i class="fas fa-dice"></i>
            Get New Joke
        </button>
        <button class="btn-secondary-custom" onclick="shareJoke()">
            <i class="fas fa-share-alt"></i>
            Share
        </button>
        <button class="btn-secondary-custom" onclick="likeJoke()">
            <i class="fas fa-heart"></i>
            Like
        </button>
        <button class="btn-secondary-custom" onclick="copyJoke()">
            <i class="fas fa-copy"></i>
            Copy
        </button>
    </div>

    <div>
        <h3 style="margin-bottom: 1rem; color: var(--text);">Recent Jokes</h3>
        <div class="history-list" id="historyList">
            <p style="text-align: center; color: var(--text-muted);">No jokes yet. Get started!</p>
        </div>
    </div>
</div>

<div class="footer">
    <p>&copy; 2024 SADA KALO FASHION - Joke Generator v1.0</p>
    <p>Powered by JokeAPI</p>
</div>

<script>
const csrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
let currentJoke = null;
let likedJokes = JSON.parse(localStorage.getItem('likedJokes')) || [];
let jokeHistory = JSON.parse(localStorage.getItem('jokeHistory')) || [];
let currentFilter = 'all';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadTheme();
    updateStats();
    getRandomJoke();
});

// Get random joke from API
async function getRandomJoke() {
    const jokeCard = document.getElementById('jokeCard');
    jokeCard.innerHTML = `
        <div class="joke-loader">
            <div class="loading"></div>
            <p>Loading a funny joke...</p>
        </div>
    `;

    try {
        let url = 'api.php?action=getJoke';
        if (currentFilter !== 'all') {
            url += '&category=' + currentFilter;
        }

        const response = await fetch(url, {
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });

        if (!response.ok) throw new Error('Failed to fetch joke');

        const data = await response.json();
        
        if (data.success) {
            currentJoke = data.joke;
            displayJoke(data.joke);
            addToHistory(data.joke);
            updateStats();
            showSuccess('Joke loaded successfully!');
        } else {
            throw new Error(data.error || 'Failed to load joke');
        }
    } catch (error) {
        console.error('Error:', error);
        showError(error.message);
        jokeCard.innerHTML = `
            <div class="joke-icon">😅</div>
            <p class="joke-text">Oops! Couldn't load a joke. Try again!</p>
        `;
    }
}

// Display joke
function displayJoke(joke) {
    const jokeCard = document.getElementById('jokeCard');
    const category = joke.category || 'General';
    let jokeContent = '';

    if (joke.type === 'single') {
        jokeContent = joke.joke;
    } else {
        jokeContent = `<strong>${joke.setup}</strong><br><br>${joke.delivery}`;
    }

    const isLiked = likedJokes.some(j => j.id === joke.id);

    jokeCard.innerHTML = `
        <div class="joke-icon">😄</div>
        <span class="joke-category">${category.toUpperCase()}</span>
        <div class="joke-text">${jokeContent}</div>
        <small style="color: var(--text-muted);">ID: ${joke.id}</small>
    `;

    // Update like button appearance
    const likeBtn = document.querySelector('[onclick="likeJoke()"]');
    if (isLiked) {
        likeBtn.style.color = '#ef4444';
    } else {
        likeBtn.style.color = 'var(--text)';
    }
}

// Filter jokes
function filterJokes(category) {
    currentFilter = category;
    
    // Update button styles
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    getRandomJoke();
}

// Add to history
function addToHistory(joke) {
    jokeHistory.unshift({
        id: joke.id,
        text: joke.type === 'single' ? joke.joke : `${joke.setup} - ${joke.delivery}`,
        category: joke.category,
        timestamp: new Date().toLocaleString()
    });

    if (jokeHistory.length > 20) {
        jokeHistory.pop();
    }

    localStorage.setItem('jokeHistory', JSON.stringify(jokeHistory));
    updateHistoryDisplay();
}

// Update history display
function updateHistoryDisplay() {
    const historyList = document.getElementById('historyList');
    
    if (jokeHistory.length === 0) {
        historyList.innerHTML = '<p style="text-align: center; color: var(--text-muted);">No jokes yet.</p>';
        return;
    }

    historyList.innerHTML = jokeHistory.map((joke, idx) => `
        <div class="history-item">
            <strong>#${idx + 1}</strong> - ${joke.text.substring(0, 50)}...
            <br><small>${joke.timestamp}</small>
        </div>
    `).join('');
}

// Like joke
function likeJoke() {
    if (!currentJoke) return;

    const index = likedJokes.findIndex(j => j.id === currentJoke.id);
    
    if (index > -1) {
        likedJokes.splice(index, 1);
        showSuccess('Removed from favorites');
    } else {
        likedJokes.push(currentJoke);
        showSuccess('Added to favorites!');
    }

    localStorage.setItem('likedJokes', JSON.stringify(likedJokes));
    updateStats();
    displayJoke(currentJoke);
}

// Copy joke
function copyJoke() {
    if (!currentJoke) return;

    let textToCopy = '';
    if (currentJoke.type === 'single') {
        textToCopy = currentJoke.joke;
    } else {
        textToCopy = `${currentJoke.setup}\n\n${currentJoke.delivery}`;
    }

    navigator.clipboard.writeText(textToCopy).then(() => {
        showSuccess('Copied to clipboard!');
    }).catch(err => {
        showError('Failed to copy');
    });
}

// Share joke
function shareJoke() {
    if (!currentJoke) return;

    let textToShare = '';
    if (currentJoke.type === 'single') {
        textToShare = currentJoke.joke;
    } else {
        textToShare = `${currentJoke.setup}\n\n${currentJoke.delivery}`;
    }

    if (navigator.share) {
        navigator.share({
            title: 'Check out this joke!',
            text: textToShare,
            url: window.location.href
        }).catch(err => console.log('Share failed:', err));
    } else {
        showError('Share not supported on this device');
    }
}

// Update stats
function updateStats() {
    const statsContainer = document.getElementById('statsContainer');
    statsContainer.innerHTML = `
        <div class="stat-box">
            <div class="stat-number">${jokeHistory.length}</div>
            <div class="stat-label">Jokes Loaded</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">${likedJokes.length}</div>
            <div class="stat-label">Favorites</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">${new Date().getHours()}</div>
            <div class="stat-label">Hour</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">😄</div>
            <div class="stat-label">Fun Level</div>
        </div>
    `;
}

// Theme toggle
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    document.querySelector('.theme-toggle i').className = 
        newTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
}

function loadTheme() {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
    
    document.querySelector('.theme-toggle i').className = 
        theme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
}

// Messages
function showError(message) {
    const errorEl = document.getElementById('errorMessage');
    errorEl.textContent = message;
    errorEl.style.display = 'block';
    setTimeout(() => {
        errorEl.style.display = 'none';
    }, 4000);
}

function showSuccess(message) {
    const successEl = document.getElementById('successMessage');
    successEl.textContent = message;
    successEl.style.display = 'block';
    setTimeout(() => {
        successEl.style.display = 'none';
    }, 3000);
}
</script>

</body>
</html>
