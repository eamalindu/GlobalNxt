<!doctype html>
<html lang="en">
<head>
    <?php include_once("includes/header.php");
    ?>
    <title>User Login | GlobalNxt x Metropolitan College</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center">
        <div class="card p-4 shadow rounded-3" style="width: 400px;">
            <div class="card-body">
                <h2 class="fw-bold text-center mb-2">Welcome, User!</h2>
                <p class="text-muted text-center">Please Log in</p>
                <form action="login.php" method="POST" class="small">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control form-control-sm" id="username" name="username" placeholder="Username" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-sm" id="password" name="password" placeholder="Password">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember">
                            <label class="form-check-label" for="remember">Remember Me</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-sm">Log in</button>
                </form>
            </div>
        </div>

    </div>

    <?php include_once("includes/footer.php");
    ?>
</body>
</html>