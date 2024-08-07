<?php
/**
 * Plugin Name: Chatbot Plugin
 * Description: A simple chatbot plugin.
 * Version: 0.1
 * Author: Swap-it-hub
 */
function chatbot_create_tables() {
    global $wpdb;

    // Include the WordPress upgrade script only once
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Define table name with WordPress table prefix
    $table_name = $wpdb->prefix . 'chatbot_question_parent';

    // SQL to create table
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        question_text TEXT NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql);

    // Define table name with WordPress table prefix
    $table_name1 = $wpdb->prefix . 'chatbot_question_child';

    // SQL to create table
    $sql1 = "CREATE TABLE $table_name1 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        question_text TEXT NOT NULL,
        question_parent_id BIGINT(20) UNSIGNED NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (question_parent_id) REFERENCES $table_name(id) ON DELETE CASCADE
    ) $charset_collate;";

    dbDelta($sql1);

    // Define table name with WordPress table prefix
    $table_name2 = $wpdb->prefix . 'form_data_savetbl';

    // SQL to create table
    $sql2 = "CREATE TABLE $table_name2 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name TEXT NOT NULL,
        mobile TEXT NOT NULL,
        location TEXT NOT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql2);
}

// Function to remove tables on plugin deactivation
function chatbot_remove_tables() {
    global $wpdb;

    // Define table names with WordPress table prefix
    $table_names = [
        $wpdb->prefix . 'chatbot_question_parent',
        $wpdb->prefix . 'chatbot_question_child',
        $wpdb->prefix . 'form_data_savetbl'
    ];

    // SQL to drop tables
    foreach ($table_names as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name;");
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'chatbot_create_tables');
register_deactivation_hook(__FILE__, 'chatbot_remove_tables');

// Add a custom admin menu for chat responses
function chatbot_register_admin_menu() {
    add_menu_page(
        'Chat Responses',
        'Chat Responses',
        'manage_options',
        'chatbot-chat-responses',
        'chatbot_chat_responses_page',
        'dashicons-format-chat',
        20
    );
}
add_action('admin_menu', 'chatbot_register_admin_menu');

// Display the chat responses page in the admin
function chatbot_chat_responses_page() {
    ?>
    <div class="wrap">
        <h1>Chat Responses</h1>
        <p>Here you can manage the chat responses collected from the chatbox.</p>
        <form method="post">
            <label>Question insert:</label><input type="text" name="questionsdb">
            <?php   global $wpdb;
            $parenttable = $wpdb->prefix . 'chatbot_question_parent';
            $query = $wpdb->prepare("SELECT * FROM {$parenttable}");
            $results = $wpdb->get_results($query);
            if(empty($results)){
                echo '';
            }else{?>
                <label for="question_child">Parent question select:</label>
                <select name="question_child" id="question_child">
                    <option>None</option>
                    <?php
                    foreach ($results as $result) {
                        echo '<option value="' . esc_attr($result->id) . '">' . esc_html($result->question_text) . '</option>';
                    }
                    ?>
                </select>
           <?php  } ?>
            <input type="submit" name="submit" value="Submit">
        </form>
        <?php
if (isset($_POST['submit']) && $_POST['submit'] != "") {
    global $wpdb;

    $questionsdb = sanitize_text_field($_POST['questionsdb']);
    $question_child = isset($_POST['question_child']) ? intval($_POST['question_child']) : 0;

    // Check if a dropdown value is selected
    if ($question_child) {
        // If a dropdown value is selected, insert into wp_chatbot_question_child table
        if (!empty($questionsdb)) {
            // Insert into wp_chatbot_question_child table
            $table_child = $wpdb->prefix . 'chatbot_question_child';
            $insert_data = array(
                'question_parent_id' => $question_child,
                'question_text' => $questionsdb
            );
            $wpdb->insert($table_child, $insert_data);
            echo 'Record inserted into wp_chatbot_question_child';
        } else {
            echo 'Please enter a value to be inserted into wp_chatbot_question_child.';
        }
    } else {
        // If no dropdown value is selected, check if the input text already exists in wp_chatbot_question_parent
        if (!empty($questionsdb)) {
            $parent_table = $wpdb->prefix . 'chatbot_question_parent';
            $query = $wpdb->prepare("SELECT * FROM {$parent_table} WHERE question_text = %s", $questionsdb);
            $existing_record = $wpdb->get_results($query);

            if (empty($existing_record)) {
                // If the input text does not exist, insert into wp_chatbot_question_parent table
                $insert_data = array(
                    'question_text' => $questionsdb
                );
                $wpdb->insert($parent_table, $insert_data);
                echo 'Record inserted into wp_chatbot_question_parent';
            } else {
                echo 'The value already exists in wp_chatbot_question_parent.';
            }
        } else {
            echo 'Please enter a value to be inserted into wp_chatbot_question_parent.';
        }
    }
}
?>
    </div>
    <?php
}

// Enqueue scripts and styles
function chatbot_enqueue_assets() {
    // Enqueue Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), null);

    // Enqueue CSS
    wp_enqueue_style('chatbot-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), null);

    // Enqueue jQuery
    wp_enqueue_script('jquery');

    // Enqueue JavaScript
    wp_enqueue_script('chatbot-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);

    // Localize script to pass PHP variables to JavaScript
    wp_localize_script('chatbot-script', 'chatbot_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'chatbot_enqueue_assets');


// Shortcode to display the chatbox
function chatbot_display_chatbox() {
    ob_start();
    ?>
<div id="chatbot-chatbox" class="chat-box-man-bg">
  <div id="chat-container" class="chat-area" style="display: none;">
    <div id="chat-header">Chat Bot</div>
    <div id="chat-close" class="chat-cross-phone">
      <div class="hook chat-cross-img"><i class="fa fa-close"></i></div>
    </div>
    
    <div class="message-window scroll-full" id="message-window" style="height: 54%; padding-bottom: 30px;">
    <div class="computer-question">
        Hello</div>
        <div class="computer-question">
            What kind of legal issue are you facing?
        </div>
</div>
    
    <div class="input-wrapper" style="bottom: 40%;">
      <input type="text" class="input-message" id="inputText" placeholder="Choose from the options given below.."  disabled="">
      <button id="send-button" class="sendButton ui-btn ui-shadow ui-corner-all" disabled="disabled">
        <img loading="lazy" src="https://lawrato.com/assets/images/sendbtn.png" id="send_button_icon">
      </button>
      <button id="skip-button" class="skipButton ui-btn ui-shadow ui-corner-all" style="width: auto; font-weight:bold;" disabled="disabled">
      </button>
    </div>

    <div class="list-wrapper scroll-full">
      <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatbot_question_parent';
        $results = $wpdb->get_results("SELECT id, question_text FROM $table_name");
        if ($results) {
          foreach ($results as $row) {
            echo '<label class="question-option">';
            echo '<input type="radio" name="legalIssue" value="' . esc_attr($row->id) . '"> ' . esc_html($row->question_text);
            echo '</label>';
          }
        } else {
          echo '<p>No options available at the moment.</p>';
        }
      ?>
    </div>
    <div class="list-wrapper1 scroll-full">

    </div>
  </div>

  <div id="chatbot-open-chatbox" class="main-chatbot-open-chatbox-icon">
    <i class="fas fa-comment"></i>
  </div>

  <div id="chat-image" style="display:none;">
    <i class="fa fa-close"></i>
  </div>
</div>
    <?php
    return ob_get_clean();
}
add_shortcode('chatbot_display', 'chatbot_display_chatbox');

// Function to display the chatbox in the footer
function chatbot_display_chatbox_footer() {
    echo do_shortcode('[chatbot_display]');
}
add_action('wp_footer', 'chatbot_display_chatbox_footer');

// AJAX handler for saving chat responses
function chatbot_save_response() {
    if (isset($_POST['action']) && $_POST['action'] == 'chatbot_save_response') {
        $response_data = sanitize_text_field($_POST['response']);
        global $wpdb;
        $parenttable2 = $wpdb->prefix . 'chatbot_question_child';
        $querydata = $wpdb->prepare(
            "SELECT * FROM {$parenttable2} WHERE question_parent_id = %d",
            $response_data
        );
        $resultschild = $wpdb->get_results($querydata);
        foreach($resultschild as $resultschildss){
        echo '<label class="question-option">';
        echo '<input type="radio" name="legalIssue" value="' .$resultschildss->id. '"> ' .$resultschildss->question_text.'
        </label>';
         }
    }
    wp_die();
}
add_action('wp_ajax_chatbot_save_response', 'chatbot_save_response');
add_action('wp_ajax_nopriv_chatbot_save_response', 'chatbot_save_response');

function handle_vendor_form_submission() {
    // Check if all required fields are set
    if (isset($_POST['question1'], $_POST['question2'], $_POST['name'], $_POST['mobileNumber'], $_POST['location'])) {
        $question1 = sanitize_text_field($_POST['question1']);
        $question2 = sanitize_text_field($_POST['question2']);
        $username = sanitize_text_field($_POST['name']);
        $usermobilenumber = sanitize_text_field($_POST['mobileNumber']);
        $userloaction = sanitize_text_field($_POST['location']);
        global $wpdb;
        $insertformdat = $wpdb->prefix . 'form_data_savetbl';
        // echo $insertformdat;
        // die;
        $insertdta = array(
            'name' => $username,
            'mobile' => $usermobilenumber,
            'location' => $userloaction,
        );
        // echo '<pre>';print_r($insertdta);echo '</pre>';
        // die;
        $insertfromtble = $wpdb->insert($insertformdat,$insertdta);
        // Prepare email content
        $to = 'rohit.thakkur2124@gmail.com';
        $subject = "Your Details Submitted";
        $message = "
            <p>Hello! What kind of legal issue are you facing? $question1</p>
            <p>What is your issue related to? $question2</p>
            <p>Name: $username</p>
            <p>Mobile: $usermobilenumber</p>
            <p>Location: $userloaction</p>
        ";
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send email
        if (wp_mail($to, $subject, $message, $headers)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Email sending failed.');
        }
    } else {
        wp_send_json_error('Invalid data.');
    }
    wp_die();
}
add_action('wp_ajax_chatbot_submit_vendor_form', 'handle_vendor_form_submission');
add_action('wp_ajax_nopriv_chatbot_submit_vendor_form', 'handle_vendor_form_submission');
?>


