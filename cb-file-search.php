<?php
/*
Plugin Name: CB File Search Plugin
Description: A plugin to search filenames in a directory with a configurable admin interface.
Version: 1.0
Author: Chillibyte - DS
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Register settings on admin menu
function fsp_register_admin_menu()
{
    add_menu_page(
        'File Search Settings',
        'File Search',
        'manage_options',
        'fsp-settings',
        'fsp_render_admin_page',
        'dashicons-search',
    );
}
add_action('admin_menu', 'fsp_register_admin_menu');

// Render admin settings page
function fsp_render_admin_page()
{
    if (!current_user_can('manage_options')) return;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        update_option('fsp_search_folder', sanitize_text_field(wp_unslash($_POST['fsp_search_folder'])));
        update_option('fsp_max_results', intval(wp_unslash($_POST['fsp_max_results'])));
        // update_option('fsp_message_text', sanitize_textarea_field(wp_unslash($_POST['fsp_message_text'])));
    }

    $folder = get_option('fsp_search_folder', WP_CONTENT_DIR . '/uploads');
    $maxResults = get_option('fsp_max_results', 10);
    // $messageText = get_option('fsp_message_text', 'Your search for [string] returns more than [n] results.');

?>
    <div class="wrap">
        <h1>File Search Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="fsp_search_folder">Search Folder</label></th>
                    <td><input type="text" name="fsp_search_folder" id="fsp_search_folder" value="<?php echo esc_attr($folder); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="fsp_max_results">Maximum Results</label></th>
                    <td><input type="number" name="fsp_max_results" id="fsp_max_results" value="<?php echo esc_attr($maxResults); ?>" class="small-text"></td>
                </tr>
                <!-- <tr>
                    <th scope="row"><label for="fsp_message_text">Message Text</label></th>
                    <td><textarea name="fsp_message_text" id="fsp_message_text" rows="3" class="large-text"><?php echo esc_textarea($messageText); ?></textarea></td>
                </tr> -->
            </table>
            <p class="submit"><button type="submit" class="button button-primary">Save Changes</button></p>
        </form>

        <div style="border:1px solid steelblue;padding: 1rem;width:min(800px,90vw)">
            <div>
                <h4>Usage:</h4>
                <pre>[file_search]</pre>
            </div>
            <div>
                <h4>CSS:</h4>
                Main container: #file-search-app<br>
                Input: #fsp-search<br>
                Button: #fsp-search-btn<br>
                Header: #fsp-results-header<br>
                Results ul: #fsp-results<br>
                File name: .file-name<br>
                File info: .file-info<br>
            </div>
        </div>

    </div>
<?php
}

// Handle AJAX search request
function fsp_handle_ajax_search()
{
    $folder = get_option('fsp_search_folder', WP_CONTENT_DIR . '/uploads');
    $baseUrl = home_url(str_replace(ABSPATH, '', $folder)); // Convert to public URL based on home URL
    $maxResults = get_option('fsp_max_results', 10);
    $messageText = pll__get_option('fsp_message_text', 'Your search for [string] returns more than [n] results.');

    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $results = [];
    $totalFiles = 0;

    if (is_dir($folder)) {
        $files = scandir($folder);
        $totalFiles = count(array_filter($files, function ($file) {
            return $file !== '.' && $file !== '..';
        }));

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && stripos($file, $search) !== false) {
                $filePath = $folder . '/' . $file;
                $results[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'date' => date("Y-m-d H:i:s", filemtime($filePath)),
                    'url' => $baseUrl . '/' . rawurlencode($file) // Include public URL for the file
                ];
            }
        }
    }

    $exceedsLimit = count($results) > $maxResults;
    $limitedResults = $exceedsLimit ? array_slice($results, 0, $maxResults) : $results;

    wp_send_json([
        'totalFiles' => $totalFiles,
        'results' => $limitedResults,
        'exceedsLimit' => $exceedsLimit,
        'searchString' => $search,
        'maxResults' => $maxResults,
        'messageText' => str_replace(['[string]', '[n]'], [$search, $maxResults], $messageText),
    ]);
}
add_action('wp_ajax_fsp_search', 'fsp_handle_ajax_search');
add_action('wp_ajax_nopriv_fsp_search', 'fsp_handle_ajax_search');

// Register shortcode
function fsp_register_shortcode()
{
    return '<div id="file-search-app">
    <div class="row">
        <label for="fsp-search" class="col-auto col-form-label">' . pll__('Search:', 'cb-aos2024') . '</label>
        <div class="col-sm-4">
        <input type="text" id="fsp-search" name="fsp-search" class="form-control">
        </div>
        <div class="col-sm-3">
            <button id="fsp-search-btn" class="btn btn-secondary">' . pll__('Search', 'cb-aos2024') . '</button>
        </div>
    </div>
        <div id="fsp-results-header"></div>
        <ul id="fsp-results"></ul>
        <script>
        document.getElementById("fsp-search-btn").addEventListener("click", function() {
            const searchQuery = document.getElementById("fsp-search").value;

            fetch("' . admin_url('admin-ajax.php') . '?action=fsp_search&search=" + encodeURIComponent(searchQuery))
                .then(response => response.json())
                .then(data => {
                    const header = document.getElementById("fsp-results-header");
                    const resultsContainer = document.getElementById("fsp-results");
                    resultsContainer.innerHTML = "";
                    
                    if (data.exceedsLimit) {
                        header.textContent = data.messageText;
                    } else {
                        header.textContent = `' . pll__('Found', 'cb-aos2024') . ' ${data.results.length} ' . pll__('result(s) out of', 'cb-aos2024') . ' ${data.totalFiles} ' . pll__('files.', 'cb-aos2024') . '`;
                    }

                    if (data.results.length > 0) {
                        data.results.forEach(file => {
                            const listItem = document.createElement("li");

                            // Create a link element for the file
                            const fileLink = document.createElement("a");
                            fileLink.classList.add("file-link");
                            fileLink.href = file.url;
                            fileLink.download = file.name;
                            fileLink.textContent = file.name;

                            // Add file info
                            const fileInfo = document.createElement("div");
                            fileInfo.classList.add("file-info");
                            fileInfo.textContent = `' . pll__('Size:', 'cb-aos2024') . ' ${(file.size / 1024).toFixed(2)} KB, ' . pll__('Date:') . ' ${file.date}`;

                            // Append elements
                            listItem.appendChild(fileLink);
                            listItem.appendChild(fileInfo);
                            resultsContainer.appendChild(listItem);
                        });

                    } else {
                        resultsContainer.innerHTML = "<li>' . pll__('No files found.', 'cb-aos2024') . '</li>";
                    }
                });
        });
        </script>
    </div>';
}
add_shortcode('file_search', 'fsp_register_shortcode');
