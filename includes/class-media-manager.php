<?php

if (!defined('ABSPATH')) {
    exit;
}

class KSMC_Media_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_ajax_actions();
    }

    private function register_ajax_actions() {
        add_action('wp_ajax_ksmc_change_owner', array($this, 'ajax_change_owner'));
        add_action('wp_ajax_ksmc_bulk_change_owner', array($this, 'ajax_bulk_change_owner'));
        add_action('wp_ajax_ksmc_update_field', array($this, 'ajax_update_field'));
    }

    public function get_media_list($args = array()) {
        $default_args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $default_args);

        return get_posts($args);
    }

    public function get_media_details($attachment_id) {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return false;
        }

        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);
        $author = get_userdata($attachment->post_author);

        return array(
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'filename' => basename($file_path),
            'file_path' => $file_path,
            'file_url' => $file_url,
            'mime_type' => $attachment->post_mime_type,
            'file_size' => file_exists($file_path) ? filesize($file_path) : 0,
            'upload_date' => $attachment->post_date,
            'author_id' => $attachment->post_author,
            'author_name' => $author ? $author->display_name : __('不明', 'kashiwazaki-seo-media-control'),
            'metadata' => $metadata,
            'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'attached_to' => $attachment->post_parent,
            'dimensions' => $this->get_image_dimensions($metadata)
        );
    }

    private function get_image_dimensions($metadata) {
        if (isset($metadata['width']) && isset($metadata['height'])) {
            return $metadata['width'] . ' x ' . $metadata['height'];
        }
        return '';
    }

    public function change_media_owner($attachment_id, $new_owner_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $attachment_id = intval($attachment_id);
        $new_owner_id = intval($new_owner_id);

        if (!get_userdata($new_owner_id)) {
            return false;
        }

        $result = wp_update_post(array(
            'ID' => $attachment_id,
            'post_author' => $new_owner_id
        ));

        return $result !== 0;
    }

    public function bulk_change_media_owner($attachment_ids, $new_owner_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $new_owner_id = intval($new_owner_id);

        if (!get_userdata($new_owner_id)) {
            return false;
        }

        $success_count = 0;
        $total_count = count($attachment_ids);

        foreach ($attachment_ids as $attachment_id) {
            if ($this->change_media_owner($attachment_id, $new_owner_id)) {
                $success_count++;
            }
        }

        return array(
            'success' => $success_count,
            'total' => $total_count,
            'errors' => $total_count - $success_count
        );
    }

    public function ajax_change_owner() {
        // AJAXリクエストかどうかを確認
        if (!wp_doing_ajax()) {
            wp_send_json_error(array(
                'message' => 'AJAXリクエストではありません',
                'details' => 'このアクションはAJAX経由でのみ実行できます'
            ));
            return;
        }

        // Nonce検証を安全に実行
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ksmc_nonce')) {
            wp_send_json_error(array(
                'message' => 'セキュリティチェックに失敗しました',
                'details' => 'Nonce verification failed'
            ));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => '権限がありません',
                'details' => 'manage_options権限が必要です'
            ));
            return;
        }

        if (!isset($_POST['attachment_id']) || !isset($_POST['new_owner_id'])) {
            wp_send_json_error(array(
                'message' => '必要なパラメータが不足しています',
                'details' => 'attachment_idまたはnew_owner_idが指定されていません'
            ));
            return;
        }

        $attachment_id = intval($_POST['attachment_id']);
        $new_owner_id = intval($_POST['new_owner_id']);

        // 添付ファイルの存在確認
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            wp_send_json_error(array(
                'message' => '添付ファイルが見つかりません',
                'details' => 'ID: ' . $attachment_id . ' の添付ファイルが存在しません'
            ));
            return;
        }

        if ($attachment->post_type !== 'attachment') {
            wp_send_json_error(array(
                'message' => '指定されたIDは添付ファイルではありません',
                'details' => 'ID: ' . $attachment_id . ' は添付ファイルではありません'
            ));
            return;
        }

        // ユーザーの存在確認
        $new_user = get_userdata($new_owner_id);
        if (!$new_user) {
            wp_send_json_error(array(
                'message' => '指定されたユーザーが見つかりません',
                'details' => 'ユーザーID: ' . $new_owner_id . ' は存在しません'
            ));
            return;
        }

        // 変更前の所有者
        $old_owner = get_userdata($attachment->post_author);

        $result = wp_update_post(array(
            'ID' => $attachment_id,
            'post_author' => $new_owner_id
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => 'データベース更新でエラーが発生しました',
                'details' => $result->get_error_message()
            ));
            return;
        }

        if ($result === 0) {
            wp_send_json_error(array(
                'message' => 'データベース更新に失敗しました',
                'details' => 'データベースの更新処理でエラーが発生しました'
            ));
            return;
        }

        // 更新後の確認
        $updated_attachment = get_post($attachment_id);
        if ($updated_attachment->post_author != $new_owner_id) {
            wp_send_json_error(array(
                'message' => '所有者の更新が反映されませんでした',
                'details' => '所有者の変更が正常に反映されませんでした'
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => '所有者を変更しました: ' . ($old_owner ? $old_owner->display_name : 'Unknown') . ' → ' . $new_user->display_name
        ));
    }

    public function ajax_bulk_change_owner() {
        // AJAXリクエストかどうかを確認
        if (!wp_doing_ajax()) {
            wp_send_json_error(array(
                'message' => 'AJAXリクエストではありません',
                'details' => 'このアクションはAJAX経由でのみ実行できます'
            ));
            return;
        }

        // Nonce検証を安全に実行
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ksmc_nonce')) {
            wp_send_json_error(array(
                'message' => 'セキュリティチェックに失敗しました',
                'details' => 'Nonce verification failed'
            ));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => '権限がありません',
                'details' => 'Current user does not have manage_options capability'
            ));
            return;
        }

        if (!isset($_POST['attachment_ids']) || !isset($_POST['new_owner_id'])) {
            wp_send_json_error(array(
                'message' => '必要なパラメータが不足しています',
                'details' => 'Missing attachment_ids or new_owner_id parameters'
            ));
            return;
        }

        if (empty($_POST['new_owner_id'])) {
            wp_send_json_error(array(
                'message' => '新しい所有者IDが空です',
                'details' => 'new_owner_id parameter is empty'
            ));
            return;
        }

        $attachment_ids = array_map('intval', $_POST['attachment_ids']);
        $new_owner_id = intval($_POST['new_owner_id']);

        if (empty($attachment_ids)) {
            wp_send_json_error(array(
                'message' => '変更するメディアが選択されていません',
                'details' => 'attachment_ids array is empty'
            ));
            return;
        }

        // ユーザーの存在確認
        $new_user = get_userdata($new_owner_id);
        if (!$new_user) {
            wp_send_json_error(array(
                'message' => '指定されたユーザーが見つかりません',
                'details' => 'User ID ' . $new_owner_id . ' does not exist'
            ));
            return;
        }

        $result = $this->bulk_change_media_owner($attachment_ids, $new_owner_id);

        if ($result && $result['success'] > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    '%d個中%d個のメディアの所有者を%sに変更しました',
                    $result['total'],
                    $result['success'],
                    $new_user->display_name
                ),
                'result' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => '一括所有者変更に失敗しました',
                'details' => $result ? 'Success: ' . $result['success'] . ', Total: ' . $result['total'] . ', Errors: ' . $result['errors'] : 'bulk_change_media_owner returned false'
            ));
        }
    }

    public function update_media_field($attachment_id, $field, $value) {
        if (!current_user_can('edit_post', $attachment_id)) {
            return false;
        }

        $attachment_id = intval($attachment_id);
        $value = sanitize_text_field($value);

        switch ($field) {
            case 'title':
                return wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_title' => $value
                )) !== 0;

            case 'caption':
                return wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_excerpt' => $value
                )) !== 0;

            case 'description':
                return wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_content' => $value
                )) !== 0;

            case 'alt_text':
                return update_post_meta($attachment_id, '_wp_attachment_image_alt', $value);

            case 'upload_date':
                $date = sanitize_text_field($value);
                if (strtotime($date)) {
                    return wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_date' => $date,
                        'post_date_gmt' => get_gmt_from_date($date)
                    )) !== 0;
                }
                return false;

            case 'owner':
                if (!current_user_can('manage_options')) {
                    return false;
                }
                $new_owner_id = intval($value);
                if (!get_userdata($new_owner_id)) {
                    return false;
                }
                return wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_author' => $new_owner_id
                )) !== 0;

            default:
                return false;
        }
    }

        public function ajax_update_field() {
        // AJAXリクエストかどうかを確認
        if (!wp_doing_ajax()) {
            wp_send_json_error(array(
                'message' => 'AJAXリクエストではありません',
                'details' => 'このアクションはAJAX経由でのみ実行できます'
            ));
            return;
        }

        // Nonce検証を安全に実行
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ksmc_nonce')) {
            wp_send_json_error(array(
                'message' => 'セキュリティチェックに失敗しました',
                'details' => 'Nonce verification failed'
            ));
            return;
        }

        if (!isset($_POST['attachment_id']) || !isset($_POST['field']) || !isset($_POST['value'])) {
            wp_send_json_error(array(
                'message' => '必要なパラメータが不足しています',
                'details' => 'Missing parameters. attachment_id: ' . (isset($_POST['attachment_id']) ? 'OK' : 'Missing') . ', field: ' . (isset($_POST['field']) ? 'OK' : 'Missing') . ', value: ' . (isset($_POST['value']) ? 'OK' : 'Missing')
            ));
            return;
        }

        $attachment_id = intval($_POST['attachment_id']);
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);

        // 添付ファイルの存在確認
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array(
                'message' => '添付ファイルが見つかりません',
                'details' => 'Attachment ID ' . $attachment_id . ' does not exist or is not an attachment'
            ));
            return;
        }

        // 編集権限の確認
        if (!current_user_can('edit_post', $attachment_id)) {
            wp_send_json_error(array(
                'message' => 'この添付ファイルを編集する権限がありません',
                'details' => 'User ID ' . get_current_user_id() . ' cannot edit attachment ' . $attachment_id
            ));
            return;
        }

        $result = $this->update_media_field($attachment_id, $field, $value);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'フィールド「' . $field . '」を更新しました'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'フィールド「' . $field . '」の更新に失敗しました',
                'details' => 'update_media_field returned false for field: ' . $field . ', value: ' . $value
            ));
        }
    }

    public function get_media_statistics() {
        global $wpdb;

        $stats = array();

        $total_media = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
        $stats['total_media'] = intval($total_media);

        $by_type = $wpdb->get_results("
            SELECT post_mime_type, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            GROUP BY post_mime_type
            ORDER BY count DESC
        ");
        $stats['by_type'] = $by_type;

        $by_author = $wpdb->get_results("
            SELECT p.post_author, u.display_name, COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
            WHERE p.post_type = 'attachment'
            GROUP BY p.post_author
            ORDER BY count DESC
        ");
        $stats['by_author'] = $by_author;

        return $stats;
    }


}
