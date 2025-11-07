<?php

if (!defined('ABSPATH')) {
    exit;
}

class KSMC_Bulk_Actions {

    public function __construct() {
        add_action('admin_init', array($this, 'handle_bulk_actions'));
        add_filter('bulk_actions-upload', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_media_bulk_action'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_action_admin_notice'));
    }

    public function add_bulk_action($bulk_actions) {
        $bulk_actions['ksmc_change_owner'] = __('所有者を変更', 'kashiwazaki-seo-media-control');
        return $bulk_actions;
    }

    public function handle_media_bulk_action($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'ksmc_change_owner') {
            return $redirect_to;
        }

        if (!current_user_can('edit_users')) {
            return $redirect_to;
        }

        if (empty($_REQUEST['new_owner'])) {
            $redirect_to = add_query_arg('ksmc_error', 'no_owner_selected', $redirect_to);
            return $redirect_to;
        }

        $new_owner_id = intval($_REQUEST['new_owner']);
        $media_manager = KSMC_Media_Manager::get_instance();
        $result = $media_manager->bulk_change_media_owner($post_ids, $new_owner_id);

        $redirect_to = add_query_arg(array(
            'ksmc_success' => $result['success'],
            'ksmc_total' => $result['total'],
            'ksmc_errors' => $result['errors']
        ), $redirect_to);

        return $redirect_to;
    }

    public function handle_bulk_actions() {
        if (!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'ksmc_change_owner') {
            return;
        }

        if (!current_user_can('edit_users')) {
            wp_die(__('権限がありません。', 'kashiwazaki-seo-media-control'));
        }

        if (empty($_REQUEST['media']) || !is_array($_REQUEST['media'])) {
            wp_redirect(admin_url('admin.php?page=kashiwazaki-seo-media-control&error=no_media_selected'));
            exit;
        }

        if (empty($_REQUEST['bulk_new_owner'])) {
            wp_redirect(admin_url('admin.php?page=kashiwazaki-seo-media-control&error=no_owner_selected'));
            exit;
        }

        $attachment_ids = array_map('intval', $_REQUEST['media']);
        $new_owner_id = intval($_REQUEST['bulk_new_owner']);

        $media_manager = KSMC_Media_Manager::get_instance();
        $result = $media_manager->bulk_change_media_owner($attachment_ids, $new_owner_id);

        $redirect_url = admin_url('admin.php?page=kashiwazaki-seo-media-control');
        $redirect_url = add_query_arg(array(
            'success' => $result['success'],
            'total' => $result['total'],
            'errors' => $result['errors']
        ), $redirect_url);

        wp_redirect($redirect_url);
        exit;
    }

    public function bulk_action_admin_notice() {
        if (!isset($_REQUEST['ksmc_success'])) {
            return;
        }

        $success = intval($_REQUEST['ksmc_success']);
        $total = intval($_REQUEST['ksmc_total']);
        $errors = intval($_REQUEST['ksmc_errors']);

        if ($success > 0) {
            $message = sprintf(
                __('%d個中%d個のメディアの所有者を変更しました。', 'kashiwazaki-seo-media-control'),
                $total,
                $success
            );

            if ($errors > 0) {
                $message .= sprintf(
                    __(' %d個のメディアで変更に失敗しました。', 'kashiwazaki-seo-media-control'),
                    $errors
                );
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('所有者の変更に失敗しました。', 'kashiwazaki-seo-media-control') . '</p></div>';
        }
    }

    public function add_owner_selection_field() {
        if (!current_user_can('edit_users')) {
            return;
        }

        $users = get_users(array('fields' => array('ID', 'display_name')));
        ?>
        <div class="ksmc-bulk-owner-selection" style="margin: 10px 0; display: none;">
            <label for="new_owner"><?php _e('新しい所有者:', 'kashiwazaki-seo-media-control'); ?></label>
            <select name="new_owner" id="new_owner">
                <option value=""><?php _e('所有者を選択', 'kashiwazaki-seo-media-control'); ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('select[name="action"], select[name="action2"]').change(function() {
                if ($(this).val() === 'ksmc_change_owner') {
                    $('.ksmc-bulk-owner-selection').show();
                } else {
                    $('.ksmc-bulk-owner-selection').hide();
                }
            });
        });
        </script>
        <?php
    }

    public function validate_bulk_action() {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'ksmc_change_owner') {
            if (empty($_REQUEST['new_owner'])) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('所有者を選択してください。', 'kashiwazaki-seo-media-control') . '</p></div>';
                });
                return false;
            }
        }
        return true;
    }
}
