<?php
/**
 * Plugin Name: khodroyi-tax
 * Description: get data from excel and show in table with ajax search 
 * Plugin URI: https://tarrahiweb.com
 * Author: farzad beheshti
 * Version: 1.1
  * Author URI:  https://tarrahiweb.com

 */
 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register the activation hook to create the table and fetch API data upon plugin activation
register_activation_hook(__FILE__, 'api_tax_plugin_activation');

// Include PhpSpreadsheet autoload
require_once(plugin_dir_path(__FILE__) . 'libs/phpspreadsheet/autoload.php');

// Add menu in WordPress admin panel
add_action('admin_menu', 'excel_upload_menu');

function excel_upload_menu() {
    add_menu_page('Excel Upload', 'Excel Upload', 'manage_options', 'excel_upload', 'excel_upload_admin_page');
}
function enqueue_jquery() {
    // Enqueue jQuery from Google CDN
    wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js', array(), '3.6.0', true);
}
add_action('wp_enqueue_scripts', 'enqueue_jquery');
 
add_action('wp_enqueue_scripts', 'enqueue_my_scripts');
function enqueue_my_css() {
    wp_enqueue_style('my-custom-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_my_css');


function enqueue_my_scripts() {
    wp_enqueue_script('my-ajax-script', plugin_dir_url(__FILE__) . 'js/my-ajax-script.js', array('jquery'));
    wp_localize_script('my-ajax-script', 'my_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}

function excel_upload_admin_page() {
    echo '<form action="" method="post" enctype="multipart/form-data">
            Select Excel File:
            <input type="file" name="excel_file" id="excel_file">
            <input type="submit" name="upload" value="Upload">
          </form>';
          
    if(isset($_POST['upload'])) {
        delete_existing_records(); // Delete existing records
        handle_excel_upload(); // Insert new records
    }
}
// Delete existing records from the table
function delete_existing_records() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'excel_data';
    $wpdb->query("DELETE FROM $table_name");
}
function api_tax_plugin_activation() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'excel_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        radif INT,
        kod_sarfasl VARCHAR(255),
        onvan_kala VARCHAR(255),
        nam_akhtsari_latin VARCHAR(255),
        nam_akhtsari VARCHAR(255),
        shenase_amomi_dakheli VARCHAR(255),
        shenase_amomi_varedat VARCHAR(255),
        maliat_bar_arzesh_afzode VARCHAR(255),
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function handle_excel_upload() {
    $uploaded_file = $_FILES['excel_file'];
    $file_path = $uploaded_file['tmp_name'];

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    global $wpdb;
    $table_name = $wpdb->prefix . 'excel_data';

    foreach($rows as $index => $row) {
        if($index == 0) continue; // Skip header
$normalized_onvan_kala = Normalizer::normalize($row[2], Normalizer::FORM_C);

        $wpdb->insert($table_name, array(
            'radif' => $row[0],
            'kod_sarfasl' => $row[1],
            'onvan_kala' => $row[2],
            'nam_akhtsari_latin' => $row[3],
            'nam_akhtsari' => $row[4],
            'shenase_amomi_dakheli' => $row[5],
            'shenase_amomi_varedat' => $row[6],
            'maliat_bar_arzesh_afzode' => $row[7]
        ));
    }

    echo "Data Uploaded Successfully!";
}

// Create a shortcode to display data
add_shortcode('display_excel_data', 'display_excel_data_function');
 function display_excel_data_function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'excel_data';

    // Check if a search query is submitted
    $search_query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';

    // Build the query condition based on the search query
    $where_condition = '';
    if (!empty($search_query)) {
        $where_condition = $wpdb->prepare("WHERE onvan_kala LIKE '%%%s%%'", $search_query);
    }

    $results = $wpdb->get_results("SELECT * FROM $table_name $where_condition", OBJECT);
  $table = '<div id="search_form">
                 <input type="text" id="search_query" placeholder="جستجو بر اساس شناسه کالا ،شناسه عمومی و ...">
                <button type="button" id="search_button">جستجو</button>
              </div>';

    $table .= '<div id="table-container">';

    $table .= '<table border="1">
                <tr>
                    <th>ردیف</th>
                    <th>کد سرفصل</th>
                    <th>عنوان کالا</th>
                    <th>نام اختصاری لاتین</th>
                    <th>نام اختصاری</th>
                    <th>شناسه عمومی داخلی</th>
                    <th>شناسه عمومی واردات</th>
                    <th>مالیات بر ارزش افزوده</th>
                </tr>';

    foreach($results as $result) {
        $table .= '<tr>
                    <td>' . $result->radif . '</td>
                    <td>' . $result->kod_sarfasl . '</td>
                    <td>' . $result->onvan_kala . '</td>
                    <td>' . $result->nam_akhtsari_latin . '</td>
                    <td>' . $result->nam_akhtsari . '</td>
                    <td>' . $result->shenase_amomi_dakheli . '</td>
                    <td>' . $result->shenase_amomi_varedat . '</td>
                    <td>' . $result->maliat_bar_arzesh_afzode . '</td>
                   </tr>';
    }

    $table .= '</table>';
    $table .= '</div>';  // Close the table-container div

    return $table;
}

// JavaScript for handling AJAX search
add_action('wp_footer', 'add_search_js');
function add_search_js() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function performSearch() {
                var searchQuery = $('#search_query').val();
                if (true) {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'search_excel_data',
                            search_query: searchQuery
                        },
                        success: function(response) {
                            console.log("Success: ", response);
                            $('#table-container').html(response); // Replace existing table with new one
                        },
                        error: function(err) {
                            console.log("Error: ", err);
                        }
                    });
                }
            }

            $('#search_query').on('keyup', function() {
                performSearch();
            });

            $('#search_button').on('click', function() {
                performSearch();
            });
        });
    </script>
    <?php
}
 add_action('wp_ajax_search_excel_data', 'search_excel_data');
add_action('wp_ajax_nopriv_search_excel_data', 'search_excel_data');


function search_excel_data() {
    // Implement your search logic here
    // Retrieve data from the database and return it as HTML
    global $wpdb;
    $table_name = $wpdb->prefix . 'excel_data';
  //  $search_query = sanitize_text_field($_POST['search_query']);
     $search_query = sanitize_text_field($_POST['search_query']);
         $search_query = str_replace('ی', 'ي', $search_query);

    
    $where_condition = '';
    if (!empty($search_query)) {
        $where_condition = $wpdb->prepare("WHERE onvan_kala LIKE '%%%s%%'", $search_query);
    }
     $results = $wpdb->get_results("SELECT * FROM $table_name $where_condition", OBJECT);

    $table = '<table border="1">
                <tr>
                    <th>ردیف</th>
                    <th>کد سرفصل</th>
                    <th>عنوان کالا</th>
                    <th>نام اختصاری لاتین</th>
                    <th>نام اختصاری</th>
                    <th>شناسه عمومی داخلی</th>
                    <th>شناسه عمومی واردات</th>
                    <th>مالیات بر ارزش افزوده</th>
                </tr>';
$row_number = 1;  // Initialize before search row number

  foreach($results as $result) {
    $table .= '<tr>
                <td>' . $row_number . '</td>  <!-- Dynamic Row Number -->
                <td>' . $result->kod_sarfasl . '</td>
                <td>' . $result->onvan_kala . '</td>
                <td>' . $result->nam_akhtsari_latin . '</td>
                <td>' . $result->nam_akhtsari . '</td>
                <td>' . $result->shenase_amomi_dakheli . '</td>
                <td>' . $result->shenase_amomi_varedat . '</td>
                <td>' . $result->maliat_bar_arzesh_afzode . '</td>
               </tr>';
    $row_number++;  // Increment row number
}

    $table .= '</table>';
 
echo $table;
    wp_die();
}
add_action('woocommerce_order_status_completed', 'set_access_expiry_date', 10, 1);

function set_access_expiry_date($order_id) {
    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        if (12881 == $product_id) {
            $user_id = $order->get_user_id();
            $current_time = current_time('timestamp');
            $expiry_time = $current_time + (15 * DAY_IN_SECONDS); // 15 days from now

            update_user_meta($user_id, 'access_expiry_date', $expiry_time);
        }
    }
}
add_action('template_redirect', 'check_access_to_page');

function check_access_to_page() {
    if (is_page(12878)) {
        $user_id = get_current_user_id();
        $expiry_time = get_user_meta($user_id, 'access_expiry_date', true);
        $current_time = current_time('timestamp');

        if (!$expiry_time || $current_time > $expiry_time) {
            // Redirect to a page where they can purchase the product
            wp_redirect(get_permalink(12881)); 
            exit;
        }
    }
}


add_action('woocommerce_thankyou', 'redirect_and_complete_order_after_purchase');

function redirect_and_complete_order_after_purchase($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        if ($product_id == 12881) {
            // Automatically complete the order
            $order->update_status('completed');

            // Redirect to specific page
            $page_id = 12878;
            $redirect_url = get_permalink($page_id);
            
            wp_redirect($redirect_url);
            exit;
        }
    }
}



function custom_page_redirectss() {
    // Check if the user is logged in and visiting the specific page with ID 12723
    if (is_user_logged_in() && is_page(12723)) {
        wp_redirect('https://mandegaracc.ir/kalas/');
        exit;
    }
}
add_action('template_redirect', 'custom_page_redirectss');





// Step 1: Form Submission Handling
add_action('gform_after_submission_36', 'custom_process_form_submission', 10, 2);
function custom_process_form_submission($entry, $form) {
    $user_id = get_current_user_id();
    if ($user_id) {
        // Update user's data (e.g., user_meta) to mark the form as submitted.
        update_user_meta($user_id, 'submitted_form_36', true);
    }
}

// Step 2: Form Display Handling
add_action('template_redirect', 'custom_check_form_access');
function custom_check_form_access() {
    if (is_page(12688)) { // Replace with the actual page ID
        $user_id = get_current_user_id();
        if ($user_id) {
            $has_submitted = get_user_meta($user_id, 'submitted_form_36', true);
            if ($has_submitted) {
                // Redirect if the user has submitted the form.
                wp_redirect('https://stuffid.mandegaracc.ir/?page_id=42');
                exit();
            }
        } else {
            // Redirect non-logged-in users to the login page.
        wp_redirect('https://mandegaracc.ir/custom-login/');
            exit();
        }
    }
}
function populate_phone_number($field, $entry, $form) {
    // Check if the form ID is 36
    if ($form['id'] == 36) {
        // Check if the field ID is input_36_3
        if ($field['id'] == 'input_36_3') { // Replace with your actual field ID
            // Get the current user's ID
            $user_id = get_current_user_id();
            
            // Check if the user is logged in and has a phone number
            if ($user_id && $user_phone = get_user_meta($user_id, 'phone_number', true)) {
                $field['defaultValue'] = $user_phone;
            }
        }
    }
    return $field;
}
add_filter('gform_field_value', 'populate_phone_number', 10, 3);

 
  function disable_right_click_on_specific_page() {
    if (is_page('12878')) { // Replace 12571 with your actual page ID
        echo '<script>
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            });
            document.addEventListener("keydown", function(e) {
                if (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 88)) { // Disable Ctrl+C and Ctrl+X
                    e.preventDefault();
                }
            });
        </script>';
    }
}
add_action('wp_footer', 'disable_right_click_on_specific_page');