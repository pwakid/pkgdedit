<?php
session_start();
//make your to add your ip address
$allowed_ips = array("127.0.0.1", "192.168.1.1"); 
// Restrict Access
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Not authorized!");
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// **Function to List Files & Directories (Recursive)**
function listFilesAndDirectories($dir, $baseDir = '') {
    $items = glob($dir . '/*');

    $directories = [];
    $files = [];

    foreach ($items as $item) {
        if (is_dir($item)) {
            $directories[] = $item;
        } else {
            $files[] = $item;
        }
    }

    natcasesort($directories);
    natcasesort($files);
    $sortedItems = array_merge($directories, $files);
    $output = '';

    foreach ($sortedItems as $item) {
        $relativePath = ltrim(str_replace($baseDir, '', $item), '/');
        $name = basename($item);

        if (is_dir($item)) {
            $subItems = listFilesAndDirectories($item, $baseDir);
            $collapseId = 'collapse-' . md5($relativePath);
            $output .= '<li class="nav-item">';
            $output .= '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '">';
            $output .= '<span class="nav-link-title">' . $name . '</span>';
            $output .= '</a>';
            $output .= '<ul class="collapse list-unstyled ms-3" id="' . $collapseId . '">' . $subItems . '</ul>';
            $output .= '</li>';
        } else {
            $output .= '<li class="nav-item">';
            $output .= '<a class="nav-link file-link" href="?file=' . $relativePath . '" data-path="?file=' . $relativePath . '">';
            $output .= '<span class="nav-link-title">' . $name . '</span>';
            $output .= '</a>';
            $output .= '</li>';
        }
    }

    return $output;
}

// Root directory
$directory = '.';
$menu = listFilesAndDirectories($directory, realpath($directory));

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_cookie'] = md5($_SESSION['csrf_token']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === "save") {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $requestedFilename = basename($_POST['file']);
    $scriptFilename = basename(__FILE__);

    // Prevent editing of this script
    if ($requestedFilename === $scriptFilename) {
        die('Editing this script file is not allowed.');
    }

    $filePath = realpath($_POST['file']);
    $filename = $filePath;
    $fileContent = $_POST['myTextArea'];

    // Save file content
    file_put_contents($filename, $fileContent);
    exit;
}

if (isset($_GET['file'])) {
    $filePath = realpath($directory . '/' . $_GET['file']);

    // Validate file path
    if (strpos($filePath, realpath($directory)) === 0 && file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
        $fileName = basename($filePath);
    } else {
        die('File not found or access denied.');
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/theme/dracula.min.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .CodeMirror {
            height: 100vh !important;
            width: 100vw !important;
        }
        .context-menu {
            position: absolute;
            background: #222;
            color: #fff;
            border-radius: 4px;
            padding: 5px 0;
            display: none;
            z-index: 1000;
            border: 1px solid #444;
        }
        .context-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .context-menu ul li {
            padding: 8px 15px;
            cursor: pointer;
            background: #222;
        }
        .context-menu ul li:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <form id="myForm" method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="file" value="<?php echo htmlspecialchars($filePath); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <textarea id="editor" name="myTextArea"><?php echo htmlspecialchars($fileContent); ?></textarea>
        <button type="submit" style="display:none;">Submit</button>
    </form>

    <div class="context-menu" id="contextMenu">
        <ul>
            <li onclick="copyText()">Copy</li>
            <li onclick="pasteText()">Paste</li>
            <li onclick="selectAllText()">Select All</li>
            <li onclick="saveFile()">Save File</li>
        </ul>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.1/codemirror.min.js"></script>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById('editor'), {
            lineNumbers: true,
            mode: "javascript",
            theme: "dracula"
        });

        var contextMenu = document.getElementById("contextMenu");

        document.addEventListener("contextmenu", function(event) {
            event.preventDefault();
            if (event.target.closest(".CodeMirror")) {
                contextMenu.style.display = "block";
                contextMenu.style.left = event.pageX + "px";
                contextMenu.style.top = event.pageY + "px";
            }
        });

        document.addEventListener("click", function() {
            contextMenu.style.display = "none";
        });

        function copyText() {
            navigator.clipboard.writeText(editor.getSelection());
            alert("Copied to clipboard");
        }

        function pasteText() {
            navigator.clipboard.readText().then(text => {
                editor.replaceSelection(text);
            });
        }

        function selectAllText() {
            editor.execCommand("selectAll");
        }

        function saveFile() {
            
                // Update the hidden textarea with the editor's content before submission
                document.getElementById("editor").value = editor.getValue();

                var formData = new FormData(document.getElementById("myForm"));

                fetch("<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    alert("File Saved!");
                    console.log("Response:", data);
                })
                .catch(error => {
                    alert("Failed to save the file! Check console for details.");
                    console.error("Error:", error);
                });
            
        }
    </script>
</body>
</html>
  
<?php exit; } ?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tabler File Browser</title>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/js/tabler.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0/dist/css/tabler.min.css">
    <style>
        .iframe-container { width: 100%; height: 100vh; border: none; }
        .close-tab { cursor: pointer; margin-left: 8px; color: red; }
    .context-menu {
    position: absolute;
    background: #222; /* Dark theme */
    color: #fff;
    border-radius: 4px;
    padding: 5px 0;
    display: none;
    z-index: 1000; /* Ensure it's above other elements */
    border: 1px solid #444;
}

.context-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.context-menu ul li {
    padding: 8px 15px;
    cursor: pointer;
    background: #222;
}

.context-menu ul li:hover {
    background: #555;
}

    </style>
</head>
<body>

    <div class="page">
        <aside class="navbar navbar-vertical navbar-expand-sm" data-bs-theme="dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="#">File Browser</a>
                </h1>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav pt-lg-3">
                      <?php echo $menu; ?>
                    </ul>
                </div>
                <div class="context-menu" id="contextMenu">
        <ul>
            <li onclick="copyText()">Copy</li>
            <li onclick="pasteText()">Paste</li>
            <li onclick="selectAllText()">Select All</li>
            <li onclick="saveFile()">Save File</li>
        </ul>
    </div>
            </div>
        </aside>

        <div class="page-wrapper">
            <div class="navbar sticky-top">
            <ul class="nav nav-tabs card-header-tabs" id="tabList">
                <li class="nav-item">
                    <a href="#tab-home" class="nav-link active" data-bs-toggle="tab">Home</a>
                </li>
            </ul>
            </div>
            <div class="tab-content" id="tabContent">
                <div class="tab-pane active" id="tab-home">
                    
                   <iframe src="https://pwarcade.com/play/all/1" width="100%" height="600px"></iframe> 
                    
                    
                  
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".file-link").forEach(link => {
                link.addEventListener("click", function(event) {
                    event.preventDefault();
                    const filePath = this.getAttribute("data-path");
                    const fileName = this.querySelector('.nav-link-title').textContent.trim();
                    addTab(filePath, fileName);
                });
            });
        });

        function addTab(filePath, fileName) {
            let existingTab = document.getElementById(`tab-${fileName}`);

            if (existingTab) {
                new bootstrap.Tab(existingTab.querySelector("a")).show();
                return;
            }

            let tabList = document.getElementById("tabList");
            let tabContent = document.getElementById("tabContent");

            let newTab = document.createElement("li");
            newTab.className = "nav-item";
            newTab.id = `tab-${fileName}`;
            newTab.innerHTML = `<a href="#tab-${fileName}-pane" class="nav-link" data-bs-toggle="tab">${fileName} <span class="close-tab" onclick="removeTab('${fileName}')">×</span></a>`;
            tabList.appendChild(newTab);

            let newContent = document.createElement("div");
            newContent.className = "tab-pane fade";
            newContent.id = `tab-${fileName}-pane`;
            newContent.innerHTML = `<iframe src="${filePath}" class="iframe-container" id=></iframe>`;
            tabContent.appendChild(newContent);

            new bootstrap.Tab(newTab.querySelector('a')).show();
        }

function removeTab(fileName) {
    if (confirm(`Are you sure you want to close ${fileName}?`)) {
        document.getElementById(`tab-${fileName}`).remove();
        document.getElementById(`tab-${fileName}-pane`).remove();
    }
}
    </script>

</body>
</html>
