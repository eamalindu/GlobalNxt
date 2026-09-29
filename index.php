<!doctype html>
<html lang="en">
<head>
    <?php include_once("includes/header.php");
    ?>
    <title>User Login | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" type="image/ico" href="favicon.ico"/>
    <style>
        html, body { height: 100%; margin: 0; }

        .split-page {
            display: flex;
            min-height: 100vh;
        }
        .split-image {
            flex: 1 1 50%;
            background: url("images/dp.jpg") left center / cover no-repeat;
            position: relative;
        }
        .split-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.5) 100%);
        }
        .split-image .split-caption {
            position: absolute;
            left: 40px;
            bottom: 40px;
            right: 40px;
            color: #fff;
            z-index: 1;
        }
        .split-image .split-caption h4 { font-weight: 600; margin-bottom: 4px; }
        .split-image .split-caption p { font-size: 13px; opacity: 0.85; margin: 0; }

        .split-form {
            flex: 1 1 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 2rem;
        }
        .split-form-inner {
            width: 100%;
            max-width: 350px;
        }

        @media (max-width: 767.98px) {
            .split-image { display: none; }
            .split-form { padding: 1.5rem; }
        }

        .credits {
            padding: 1rem;
            background: #212529;
            color: #fff;
            margin: 0;
        }
    </style>
</head>
<body>

<div class="split-page">
    <div class="split-image">
        <div class="split-caption">
            <h4>GlobalNxt &times; Metropolitan College</h4>
            <p>Document Verification Platform</p>
        </div>
    </div>

    <div class="split-form">
        <div class="split-form-inner">

            <img src="images/logo_new.png" width="80%" class="d-block mx-auto" alt="logo">
            <h4 class="fw-bold mb-1 mt-3 text-center">Welcome back</h4>
            <p class="text-muted small mb-0 text-center">Login to your account</p>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'empty'): ?>
                    <div class="alert alert-warning p-2 small mt-4"><i class="bi bi-exclamation-triangle-fill"></i> Please fill in all fields.</div>
                <?php elseif ($_GET['error'] === 'invalid'): ?>
                    <div class="alert alert-danger p-2 small mt-4"><i class="bi bi-x-circle"></i> Invalid username or password.</div>
                <?php endif; ?>
            <?php endif; ?>

            <form id="loginForm" method="POST" class="mt-4 small" action="login.php">

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label fw-medium">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="text" class="form-control form-control-sm" id="username" name="username" placeholder="Enter your username" required autocomplete="off">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label fw-medium mb-0">Password</label>
                        <a href="forgot_password.php" class="small text-dark text-decoration-none">Forgot
                            password?</a>
                    </div>
                    <div class="input-group mt-1">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control form-control-sm" id="password" name="password" placeholder="Enter your password" required>
                        <button class="btn btn-outline-dark" type="button" id="togglePassword" tabindex="-1">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                        <div class="invalid-feedback">Password must be at least 6 characters.</div>
                    </div>
                </div>

                <!-- Remember me -->
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                        <label class="form-check-label small" for="rememberMe">Remember me</label>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-dark btn-sm btn fw-semibold">Log In</button>
                </div>


                <!-- Register link -->
                <p class="text-center text-muted small mb-0">
                    Document Verification Platform
                </p>

            </form>
        </div>
    </div>
</div>

<script src="js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const pwd = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    })
</script>
</body>
</html>