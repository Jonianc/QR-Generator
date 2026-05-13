<?php
/**
 * Plugin Name: OT QR Automator
 * Description: Frontend sin header/footer para subir PDF de OT y obtener carátula QR. Subida pública con clave y gestor privado para usuarios logueados con permisos. En subida: SOLO PDF (OT/modelo/cliente se extraen del nombre del archivo).
 * Version: 0.5.0
 * Author: Rocket Solutions
 */
if (!defined('ABSPATH')) { exit; }

final class OTQR_Automator {
    const CPT = 'otqr';
    const MENU_SLUG = 'otqr-automator';
    const OPT_PUBLIC_KEY = 'otqr_public_upload_key';
    const OPT_VERSION = 'otqr_plugin_version';
    const VERSION = '0.5.0';

    const META_ATTACHMENT_ID = '_otqr_attachment_id';
    const META_PDF_PRIVATE_PATH = '_otqr_pdf_private_path';
    const META_PDF_ORIGINAL_NAME = '_otqr_pdf_original_name';
    const META_PDF_SIZE = '_otqr_pdf_size';
    const META_PDF_HASH = '_otqr_pdf_hash';
    const META_PDF_UPDATED_AT = '_otqr_pdf_updated_at';
    const META_MODELO = '_otqr_modelo';
    const META_CLIENTE = '_otqr_cliente';
    const META_PUBLIC_TOKEN = '_otqr_public_token';
    const META_BOX_ID = '_otqr_box_id';
    const OPT_BOXES = 'otqr_boxes';

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('init', [__CLASS__, 'add_rewrites']);
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        add_action('template_redirect', [__CLASS__, 'handle_public_routes']);

        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_otqr_save_key', [__CLASS__, 'handle_admin_save_key']);
        add_action('init', [__CLASS__, 'maybe_run_upgrade'], 20);

        register_activation_hook(__FILE__, [__CLASS__, 'on_activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'on_deactivate']);
    }

    public static function register_cpt() {
        register_post_type(self::CPT, [
            'labels' => ['name' => 'OT QR','singular_name' => 'OT QR'],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title'],
            'rewrite' => ['slug' => 'ot','with_front' => false],
        ]);
    }

    public static function add_rewrites() {
        add_rewrite_rule('^otqr/?$', 'index.php?otqr_home=1', 'top');          // buscar (sin clave)
        add_rewrite_rule('^otqr/upload/?$', 'index.php?otqr_upload=1', 'top'); // subir (con clave) => SOLO PDF
        add_rewrite_rule('^otqr/manage/?$', 'index.php?otqr_manage=1', 'top'); // gestionar (login + capability)

        add_rewrite_rule('^otqr/cover/([a-f0-9]{32})/?$', 'index.php?otqr_token_cover=1&otqr_token=$matches[1]', 'top');
        add_rewrite_rule('^otqr/ver/([a-f0-9]{32})/?$', 'index.php?otqr_token_view=1&otqr_token=$matches[1]', 'top');

        add_rewrite_rule('^ot/([0-9]+)/cover/?$', 'index.php?otqr_cover=1&otqr_num=$matches[1]', 'top');
        add_rewrite_rule('^ot/([0-9]+)/?$', 'index.php?post_type=' . self::CPT . '&name=$matches[1]&otqr_num=$matches[1]', 'top');
    }

    public static function register_query_vars($vars) {
        $vars[]='otqr_cover'; $vars[]='otqr_num'; $vars[]='otqr_home'; $vars[]='otqr_upload'; $vars[]='otqr_manage'; $vars[]='otqr_token'; $vars[]='otqr_token_view'; $vars[]='otqr_token_cover';
        return $vars;
    }

    private static function generate_key($len = 20) {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out='';
        for ($i=0;$i<$len;$i++) { $out .= $alphabet[random_int(0, strlen($alphabet)-1)]; }
        return $out;
    }

    public static function on_activate() {
        self::register_cpt(); self::add_rewrites();
        if (!get_option(self::OPT_PUBLIC_KEY)) { add_option(self::OPT_PUBLIC_KEY, self::generate_key(20)); }
        self::ensure_tokens_for_existing_ots();
        self::ensure_default_boxes();
        self::ensure_private_pdf_storage();
        self::migrate_public_attachments_to_private();
        update_option(self::OPT_VERSION, self::VERSION);
        flush_rewrite_rules();
    }
    public static function on_deactivate() { flush_rewrite_rules(); }

    public static function maybe_run_upgrade() {
        $installed=get_option(self::OPT_VERSION,'');
        if (!is_string($installed) || version_compare($installed,self::VERSION,'<')) {
            self::add_rewrites();
            self::ensure_tokens_for_existing_ots();
            self::ensure_default_boxes();
            self::ensure_private_pdf_storage();
            self::migrate_public_attachments_to_private();
            flush_rewrite_rules();
            update_option(self::OPT_VERSION, self::VERSION);
        }
    }

    public static function admin_menu() {
        add_menu_page('OT QR','OT QR','manage_options',self::MENU_SLUG,[__CLASS__,'render_admin_page'],'dashicons-qr',58);
        add_submenu_page(self::MENU_SLUG, 'Gestión de BOX', 'BOX', 'manage_options', 'otqr-boxes', [__CLASS__, 'render_boxes_page']);
    }
    private static function generate_box_id() { return 'box_'.bin2hex(random_bytes(6)); }
    private static function get_boxes() {
        $boxes=get_option(self::OPT_BOXES,[]);
        if (!is_array($boxes)) return [];
        $out=[];
        foreach($boxes as $b){
            if (!is_array($b) || empty($b['id'])) continue;
            $id=sanitize_key($b['id']);
            if ($id==='') continue;
            $out[]=['id'=>$id,'name'=>isset($b['name'])?self::normalize_text($b['name']):'','active'=>!empty($b['active']),'created_at'=>isset($b['created_at'])?sanitize_text_field($b['created_at']):''];
        }
        return $out;
    }
    private static function save_boxes($boxes){ update_option(self::OPT_BOXES, array_values($boxes)); }
    private static function ensure_default_boxes() {
        $boxes=self::get_boxes();
        if (!empty($boxes)) return;
        self::save_boxes([['id'=>self::generate_box_id(),'name'=>'BOX 1','active'=>true,'created_at'=>current_time('mysql')]]);
    }
    private static function get_active_boxes() {
        return array_values(array_filter(self::get_boxes(), function($b){ return !empty($b['active']); }));
    }
    private static function get_box_by_id($box_id) {
        foreach(self::get_boxes() as $box){ if ($box['id']===$box_id) return $box; }
        return null;
    }
    private static function normalize_box_name_for_match($name){
        $name=sanitize_text_field($name);
        $name=self::normalize_text($name);
        return function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name);
    }
    private static function resolve_box_id_from_name($name){
        $clean=sanitize_text_field($name);
        $clean=self::normalize_text($clean);
        $len=function_exists('mb_strlen')?mb_strlen($clean,'UTF-8'):strlen($clean);
        if ($len<2 || $len>80) return '';
        $needle=self::normalize_box_name_for_match($clean);
        foreach(self::get_boxes() as $box){
            $existing=isset($box['name'])?self::normalize_box_name_for_match($box['name']):'';
            if ($existing!=='' && $existing===$needle) return $box['id'];
        }
        $boxes=self::get_boxes();
        $new_id=self::generate_box_id();
        $boxes[]=['id'=>$new_id,'name'=>$clean,'active'=>true,'created_at'=>current_time('mysql')];
        self::save_boxes($boxes);
        return $new_id;
    }
    private static function get_box_label_for_ot($post_id){
        $box_id=get_post_meta($post_id,self::META_BOX_ID,true);
        if (!is_string($box_id) || $box_id==='') return 'Sin asignar';
        $box=self::get_box_by_id(sanitize_key($box_id));
        if (!$box) return 'BOX eliminado/inactivo';
        if (empty($box['active'])) return 'BOX eliminado/inactivo';
        return $box['name']!==''?$box['name']:'BOX sin nombre';
    }
    private static function box_has_assigned_ots($box_id){
        $found=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_BOX_ID,'value'=>$box_id,'compare'=>'=']]]);
        return !empty($found);
    }

    private static function normalize_ot_number($v){ $v=is_string($v)?trim($v):''; return preg_replace('/\D+/','',$v); }
    private static function normalize_text($v){ $v=is_string($v)?trim($v):''; $v=preg_replace('/\s+/',' ',$v); return $v; }

    /**
     * Extrae [ot, modelo, cliente] desde el nombre de archivo.
     * Requerido: OT y 2 comas (3 partes).
     * Ej: OT 19230, MF3307, GARCES -MELIPILLA.pdf
     */
    private static function parse_from_filename_strict($filename) {
        $base = wp_basename($filename);
        $base = preg_replace('/\.[^.]+$/', '', $base);

        $s = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], '-', $base);
        $s = str_replace([';','|','_'], ',', $s);

        $parts = array_values(array_filter(array_map('trim', explode(',', $s)), function($p){ return $p !== ''; }));

        if (count($parts) < 3) {
            return ['ok'=>false,'ot'=>'','modelo'=>'','cliente'=>'','reason'=>'Formato inválido. Debe ser: OT 19230, MF3307, CLIENTE'];
        }

        $ot = '';
        if (preg_match('/OT\s*([0-9]+)/i', $parts[0], $m)) $ot = $m[1];
        else if (preg_match('/([0-9]{3,})/', $parts[0], $m)) $ot = $m[1];

        $modelo = $parts[1];
        $cliente = implode(', ', array_slice($parts, 2));

        $ot = self::normalize_ot_number($ot);
        $modelo = self::normalize_text($modelo);
        $cliente = self::normalize_text($cliente);

        if ($ot === '' || $modelo === '' || $cliente === '') {
            return ['ok'=>false,'ot'=>$ot,'modelo'=>$modelo,'cliente'=>$cliente,'reason'=>'No se pudo detectar OT/modelo/cliente desde el nombre.'];
        }

        return ['ok'=>true,'ot'=>$ot,'modelo'=>$modelo,'cliente'=>$cliente,'reason'=>''];
    }

    private static function get_ot_post_by_number($ot_number){
        $ot_number=self::normalize_ot_number($ot_number);
        if ($ot_number==='') return null;
        $posts=get_posts(['post_type'=>self::CPT,'name'=>$ot_number,'post_status'=>'any','numberposts'=>1,'fields'=>'ids']);
        return !empty($posts)?intval($posts[0]):null;
    }

    private static function upsert_ot_post($ot_number){
        $existing_id=self::get_ot_post_by_number($ot_number);
        $id=$existing_id;
        if (!$id) $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'publish','post_title'=>'OT '.$ot_number,'post_name'=>$ot_number]);
        else wp_update_post(['ID'=>$id,'post_title'=>'OT '.$ot_number,'post_name'=>$ot_number]);
        if (is_wp_error($id)||!$id) return 0;
        return intval($id);
    }

    private static function get_public_key(){ $k=get_option(self::OPT_PUBLIC_KEY,''); return is_string($k)?trim($k):''; }

    private static function get_private_pdf_dir(){
        $uploads=wp_get_upload_dir();
        $base=isset($uploads['basedir'])?$uploads['basedir']:'';
        if (!is_string($base) || $base==='') return '';
        return trailingslashit($base).'otqr-private';
    }
    private static function ensure_private_pdf_storage(){
        $dir=self::get_private_pdf_dir(); if ($dir==='') return false;
        if (!file_exists($dir)) wp_mkdir_p($dir);
        if (!is_dir($dir) || !is_writable($dir)) return false;
        if (!file_exists($dir.'/index.php')) file_put_contents($dir.'/index.php', "<?php\n");
        if (!file_exists($dir.'/.htaccess')) file_put_contents($dir.'/.htaccess', "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        if (!file_exists($dir.'/web.config')) file_put_contents($dir.'/web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\"/>\n        <add accessType=\"Deny\" users=\"*\"/>\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n");
        return true;
    }
    private static function sanitize_pdf_filename($name){
        $name=is_string($name)?trim($name):'documento.pdf';
        $clean=sanitize_file_name($name);
        if ($clean==='') $clean='documento.pdf';
        if (!preg_match('/\.pdf$/i',$clean)) $clean.='.pdf';
        return $clean;
    }
    private static function generate_private_pdf_name(){ return 'otqr_'.bin2hex(random_bytes(16)).'.pdf'; }
    private static function save_uploaded_pdf_privately($file){
        if (!self::ensure_private_pdf_storage()) return ['ok'=>false,'error'=>'No se pudo preparar el almacenamiento privado.'];
        $tmp=isset($file['tmp_name'])?$file['tmp_name']:'';
        if (!is_string($tmp) || $tmp==='' || !is_uploaded_file($tmp)) return ['ok'=>false,'error'=>'Archivo temporal inválido.'];
        $dest=trailingslashit(self::get_private_pdf_dir()).self::generate_private_pdf_name();
        if (!move_uploaded_file($tmp,$dest)) return ['ok'=>false,'error'=>'No se pudo mover el PDF al almacenamiento privado.'];
        $size=@filesize($dest);
        if (!$size || $size<1) { @unlink($dest); return ['ok'=>false,'error'=>'El PDF privado quedó vacío.']; }
        return ['ok'=>true,'path'=>$dest,'size'=>intval($size),'hash'=>@hash_file('sha256',$dest)];
    }
    private static function get_valid_private_pdf_path($post_id){
        $path=get_post_meta($post_id,self::META_PDF_PRIVATE_PATH,true);
        if (!is_string($path) || $path==='') return '';
        $base=realpath(self::get_private_pdf_dir()); $real=realpath($path);
        if (!$base || !$real) return '';
        if (strpos($real,$base.DIRECTORY_SEPARATOR)!==0 && $real!==$base) return '';
        if (!is_file($real) || !is_readable($real)) return '';
        return $real;
    }
    private static function set_private_pdf_meta($post_id,$path,$original_name,$size,$hash){
        update_post_meta($post_id,self::META_PDF_PRIVATE_PATH,$path);
        update_post_meta($post_id,self::META_PDF_ORIGINAL_NAME,self::sanitize_pdf_filename($original_name));
        update_post_meta($post_id,self::META_PDF_SIZE,intval($size));
        update_post_meta($post_id,self::META_PDF_HASH,is_string($hash)?$hash:'');
        update_post_meta($post_id,self::META_PDF_UPDATED_AT,current_time('mysql'));
    }
    private static function migrate_public_attachments_to_private(){
        if (!self::ensure_private_pdf_storage()) return;
        $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
        foreach($ids as $pid){
            $pid=intval($pid); $private=get_post_meta($pid,self::META_PDF_PRIVATE_PATH,true);
            if (is_string($private) && $private!=='') continue;
            $attach_id=intval(get_post_meta($pid,self::META_ATTACHMENT_ID,true)); if (!$attach_id) continue;
            $source=get_attached_file($attach_id);
            if (!is_string($source) || !is_file($source) || !is_readable($source)) continue;
            $dest=trailingslashit(self::get_private_pdf_dir()).self::generate_private_pdf_name();
            if (!copy($source,$dest)) continue;
            $size=@filesize($dest); if (!$size || $size<1) { @unlink($dest); continue; }
            $original_name=get_the_title($attach_id); if (!is_string($original_name)||$original_name==='') $original_name=wp_basename($source);
            self::set_private_pdf_meta($pid,$dest,$original_name,intval($size),@hash_file('sha256',$dest));
            wp_delete_attachment($attach_id,true); delete_post_meta($pid,self::META_ATTACHMENT_ID);
        }
    }


    private static function generate_public_token() {
        return bin2hex(random_bytes(16));
    }

    private static function token_exists($token) {
        $found=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_PUBLIC_TOKEN,'value'=>$token,'compare'=>'=']]]);
        return !empty($found);
    }

    private static function ensure_public_token($post_id) {
        $token=get_post_meta($post_id,self::META_PUBLIC_TOKEN,true);
        if (is_string($token) && preg_match('/^[a-f0-9]{32}$/',$token)) return $token;
        do { $token=self::generate_public_token(); } while(self::token_exists($token));
        update_post_meta($post_id,self::META_PUBLIC_TOKEN,$token);
        return $token;
    }

    private static function ensure_tokens_for_existing_ots() {
        $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
        foreach($ids as $id){ self::ensure_public_token(intval($id)); }
    }

    private static function get_ot_post_by_token($token){
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/',$token)) return 0;
        $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_PUBLIC_TOKEN,'value'=>$token,'compare'=>'=']]]);
        return !empty($ids)?intval($ids[0]):0;
    }
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $notice=isset($_GET['otqr_notice'])?sanitize_text_field($_GET['otqr_notice']):'';
        $admin_post=admin_url('admin-post.php');
        $nonce_key=wp_create_nonce('otqr_key');

        $key=self::get_public_key();
        $upload_url=add_query_arg(['k'=>$key], home_url('/otqr/upload/'));
        $manage_url=home_url('/otqr/manage/');
        ?>
        <div class="wrap">
            <h1>OT QR Automator</h1>
            <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

            <h2>Links</h2>
            <p><strong>Subir (solo PDF):</strong> <a href="<?php echo esc_url($upload_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($upload_url); ?></a></p>
            <p><strong>Gestionar:</strong> <a href="<?php echo esc_url($manage_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($manage_url); ?></a></p>

            <p class="description">
                Nombre PDF <strong>obligatorio</strong>: <code>OT 19230, MF3307, GARCES -MELIPILLA.pdf</code>
                (extrae OT + modelo + cliente para la carátula).
            </p>

            <form method="post" action="<?php echo esc_url($admin_post); ?>" style="margin-top:12px;">
                <input type="hidden" name="action" value="otqr_save_key" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_key); ?>" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="public_key">Clave</label></th>
                        <td>
                            <input name="public_key" id="public_key" type="text" class="regular-text" value="<?php echo esc_attr($key); ?>" />
                            <p class="description">Puedes regenerarla si se filtró.</p>
                            <button class="button" type="submit" name="regen" value="1">Regenerar</button>
                            <button class="button button-primary" type="submit">Guardar</button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <?php
    }

    public static function handle_admin_save_key() {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
        check_admin_referer('otqr_key');

        $regen=isset($_POST['regen'])?sanitize_text_field(wp_unslash($_POST['regen'])):'';
        if ($regen==='1') update_option(self::OPT_PUBLIC_KEY, self::generate_key(20));
        else {
            $k=isset($_POST['public_key'])?sanitize_text_field(wp_unslash($_POST['public_key'])):'';
            $k=preg_replace('/[^A-Za-z0-9]/','',$k);
            if (strlen($k)<8) {
                wp_redirect(add_query_arg(['page'=>self::MENU_SLUG,'otqr_notice'=>rawurlencode('Clave muy corta (mínimo 8).')], admin_url('admin.php')));
                exit;
            }
            update_option(self::OPT_PUBLIC_KEY, $k);
        }
        wp_redirect(add_query_arg(['page'=>self::MENU_SLUG,'otqr_notice'=>rawurlencode('Clave actualizada.')], admin_url('admin.php')));
        exit;
    }
    public static function render_boxes_page() {
        if (!current_user_can('manage_options')) return;
        self::ensure_default_boxes();
        $msg=''; $err='';
        if ($_SERVER['REQUEST_METHOD']==='POST'){
            $action=isset($_POST['box_action'])?sanitize_text_field(wp_unslash($_POST['box_action'])):'';
            check_admin_referer('otqr_boxes_'.$action);
            $boxes=self::get_boxes();
            if ($action==='create'){
                $name=isset($_POST['box_name'])?self::normalize_text(wp_unslash($_POST['box_name'])):'';
                if ($name==='') $err='Nombre de BOX obligatorio.';
                else { $boxes[]=['id'=>self::generate_box_id(),'name'=>$name,'active'=>true,'created_at'=>current_time('mysql')]; self::save_boxes($boxes); $msg='BOX creada.'; }
            } else {
                $box_id=isset($_POST['box_id'])?sanitize_key(wp_unslash($_POST['box_id'])):'';
                foreach($boxes as $idx=>$box){
                    if ($box['id']!==$box_id) continue;
                    if ($action==='rename'){ $name=isset($_POST['box_name'])?self::normalize_text(wp_unslash($_POST['box_name'])):''; if($name===''){ $err='Nombre inválido.'; } else { $boxes[$idx]['name']=$name; self::save_boxes($boxes); $msg='BOX actualizada.'; } }
                    if ($action==='toggle'){ $boxes[$idx]['active']=empty($boxes[$idx]['active']); self::save_boxes($boxes); $msg='Estado de BOX actualizado.'; }
                    if ($action==='delete'){ if(self::box_has_assigned_ots($box_id)){ $err='No se puede eliminar BOX con OTs asignadas.'; } else { unset($boxes[$idx]); self::save_boxes($boxes); $msg='BOX eliminada.'; } }
                    break;
                }
            }
        }
        $boxes=self::get_boxes();
        ?>
        <div class="wrap"><h1>Gestión de BOX</h1>
            <?php if ($msg): ?><div class="notice notice-success"><p><?php echo esc_html($msg); ?></p></div><?php endif; ?>
            <?php if ($err): ?><div class="notice notice-error"><p><?php echo esc_html($err); ?></p></div><?php endif; ?>
            <h2>Crear BOX</h2>
            <form method="post"><?php wp_nonce_field('otqr_boxes_create'); ?><input type="hidden" name="box_action" value="create"/><input type="text" name="box_name" required /><button class="button button-primary" type="submit">Crear</button></form>
            <h2 style="margin-top:20px;">BOX existentes</h2>
            <table class="widefat"><thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                <?php foreach($boxes as $box): ?>
                    <tr>
                        <td>
                            <form method="post" style="display:flex;gap:8px;"><?php wp_nonce_field('otqr_boxes_rename'); ?><input type="hidden" name="box_action" value="rename"/><input type="hidden" name="box_id" value="<?php echo esc_attr($box['id']); ?>"/><input type="text" name="box_name" value="<?php echo esc_attr($box['name']); ?>" required /><button class="button" type="submit">Guardar</button></form>
                        </td>
                        <td><?php echo !empty($box['active']) ? 'Activo' : 'Inactivo'; ?></td>
                        <td style="display:flex;gap:8px;">
                            <form method="post"><?php wp_nonce_field('otqr_boxes_toggle'); ?><input type="hidden" name="box_action" value="toggle"/><input type="hidden" name="box_id" value="<?php echo esc_attr($box['id']); ?>"/><button class="button" type="submit"><?php echo !empty($box['active']) ? 'Desactivar' : 'Activar'; ?></button></form>
                            <form method="post" onsubmit="return confirm('¿Eliminar BOX?');"><?php wp_nonce_field('otqr_boxes_delete'); ?><input type="hidden" name="box_action" value="delete"/><input type="hidden" name="box_id" value="<?php echo esc_attr($box['id']); ?>"/><button class="button button-link-delete" type="submit">Eliminar</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    // ---------- Frontend HTML ----------
    private static function send_minimal_html($title,$body_html){
        nocache_headers(); header('Content-Type: text/html; charset=UTF-8');
        ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title><?php echo esc_html($title); ?></title>
        <style>
        :root{--border:#e6e6e6;--text:#111;--muted:#666;--bg:#fff;--danger:#b00020;--ok:#137333;--surface:#fafafa;--surface-2:#f4f4f4;}
        *{box-sizing:border-box} body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--text)}
        .wrap{max-width:980px;margin:34px auto;padding:0 16px}
        .card{border:1px solid var(--border);border-radius:14px;padding:18px;background:#fff}
        h1{font-size:18px;margin:0 0 10px}
        p{margin:10px 0;color:var(--muted);font-size:13px;line-height:1.45}
        label{display:block;font-size:13px;margin:0 0 6px}
        input{width:100%;padding:12px;border-radius:10px;border:1px solid var(--border);font-size:16px}
        input[type=file]{padding:10px;font-size:13px}
        .row{display:flex;gap:10px;margin-top:12px;align-items:center;flex-wrap:wrap}
        .btn{appearance:none;border:1px solid var(--border);background:#fff;padding:10px 12px;border-radius:10px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
        .btn.primary{border-color:#111;color:#fff;background:#111}
        .btn.danger{border-color:var(--danger);color:#fff;background:var(--danger)}
        .result{margin-top:14px;padding-top:14px;border-top:1px dashed var(--border);font-size:13px}
        a{color:#111} code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
        .error,.ok{font-weight:600;padding:10px 12px;border-radius:10px;border:1px solid transparent}
        .error{color:var(--danger);background:#fff5f7;border-color:#ffd9e1}
        .ok{color:var(--ok);background:#f4fff7;border-color:#cfeedd}
        table{width:100%;border-collapse:collapse;margin-top:12px}
        th,td{font-size:13px;text-align:left;padding:10px 8px;border-bottom:1px solid var(--border);vertical-align:top}
        th{color:#333;font-weight:700;background:var(--surface);position:sticky;top:0}
        tbody tr:hover{background:#fcfcfc}
        .small{font-size:12px;color:var(--muted)} .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--surface-2);font-size:12px}
        .actions{white-space:nowrap}
        .actions .btn{padding:7px 9px;font-size:12px}
        .grid{display:grid;grid-template-columns:1fr;gap:12px}
        .toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .toolbar .title p{margin:4px 0 0 0}
        .table-wrap{overflow:auto}
        .section-title{margin:0 0 8px 0;font-size:14px}
        .section{padding:12px;border:1px solid var(--border);border-radius:10px;background:#fff}
        .section + .section{margin-top:10px}
        @media(min-width:820px){.grid{grid-template-columns:1.1fr 0.9fr}}
        @media(max-width:819px){.wrap{margin:16px auto}.card{padding:14px}}
        </style></head><body><div class="wrap"><?php echo $body_html; // phpcs:ignore ?></div></body></html>
        <?php
    }

    private static function public_key_ok(){
        $expected=self::get_public_key(); if ($expected==='') return false;
        $k=isset($_GET['k'])?sanitize_text_field(wp_unslash($_GET['k'])):'';
        $k=preg_replace('/[^A-Za-z0-9]/','',$k);
        return hash_equals($expected,$k);
    }
    private static function key_nonce_action(){
        $k=isset($_GET['k'])?sanitize_text_field(wp_unslash($_GET['k'])):'';
        $k=preg_replace('/[^A-Za-z0-9]/','',$k);
        return 'otqr_pub_'.$k;
    }

    private static function insert_pdf_as_attachment($file){
        $file_path=$file['file']; $file_url=$file['url']; $filename=wp_basename($file_path);
        $attachment=['guid'=>$file_url,'post_mime_type'=>'application/pdf','post_title'=>preg_replace('/\.[^.]+$/','',$filename),'post_content'=>'','post_status'=>'inherit'];
        $attach_id=wp_insert_attachment($attachment,$file_path,0);
        if (!is_wp_error($attach_id)){
            require_once ABSPATH.'wp-admin/includes/image.php';
            wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id,$file_path));
            return intval($attach_id);
        }
        return 0;
    }

    private static function handle_frontend_upload(){
        $title='Subir OT'; $err=''; $msg=''; $cover_url='';
        if (!self::public_key_ok()){ self::send_minimal_html($title,'<div class="card"><h1>Subir OT</h1><p class="error">Acceso denegado.</p></div>'); exit; }

        $nonce_action=self::key_nonce_action();
        $k=sanitize_text_field(wp_unslash($_GET['k']));
        $manage_url=home_url('/otqr/manage/');
        $form_action=add_query_arg(['k'=>$k], home_url('/otqr/upload/'));
        $nonce_val=wp_create_nonce($nonce_action);
        self::ensure_default_boxes();
        $active_boxes=self::get_active_boxes();

        if ($_SERVER['REQUEST_METHOD']==='POST'){
            $nonce=isset($_POST['_wpnonce'])?sanitize_text_field(wp_unslash($_POST['_wpnonce'])):'';
            if (!wp_verify_nonce($nonce,$nonce_action)) $err='Sesión expirada. Recarga la página.';
            elseif (empty($_POST['box_id'])) $err='Debes seleccionar un BOX activo.';
            elseif (empty($_FILES['ot_pdf'])||empty($_FILES['ot_pdf']['tmp_name'])||!is_uploaded_file($_FILES['ot_pdf']['tmp_name'])) $err='Debes seleccionar un PDF.';
            else {
                $box_id=sanitize_key(wp_unslash($_POST['box_id']));
                if ($box_id==='manual') {
                    $box_manual=isset($_POST['box_manual'])?sanitize_text_field(wp_unslash($_POST['box_manual'])):'';
                    $box_manual=self::normalize_text($box_manual);
                    $box_len=function_exists('mb_strlen')?mb_strlen($box_manual,'UTF-8'):strlen($box_manual);
                    if ($box_len<2 || $box_len>80) $err='Nombre del BOX inválido (2 a 80 caracteres).';
                    else {
                        $box_id=self::resolve_box_id_from_name($box_manual);
                        if ($box_id==='') $err='No se pudo resolver el BOX manual.';
                    }
                } else {
                    $box=self::get_box_by_id($box_id);
                    if (!$box || empty($box['active'])) $err='BOX inválido o inactivo.';
                }

                if ($err==='') {
                    $filetype=wp_check_filetype_and_ext($_FILES['ot_pdf']['tmp_name'], $_FILES['ot_pdf']['name']);
                    if ($filetype['ext']!=='pdf') $err='El archivo debe ser PDF.';
                    else {
                        $max=20*1024*1024;
                        if (!empty($_FILES['ot_pdf']['size']) && intval($_FILES['ot_pdf']['size'])>$max) $err='Archivo demasiado grande (máx 20MB).';
                        else {
                            $parsed=self::parse_from_filename_strict($_FILES['ot_pdf']['name']);
                            if (!$parsed['ok']) $err=$parsed['reason'];
                            else {
                                $ot=$parsed['ot']; $modelo=$parsed['modelo']; $cliente=$parsed['cliente'];
                                $otqr_id=self::upsert_ot_post($ot);
                                if (!$otqr_id) $err='Error guardando la OT.';
                                else {
                                    $old_private=self::get_valid_private_pdf_path($otqr_id);
                                    $saved=self::save_uploaded_pdf_privately($_FILES['ot_pdf']);
                                    if (!$saved['ok']) $err=$saved['error'];
                                    else {
                                        self::set_private_pdf_meta($otqr_id,$saved['path'],$_FILES['ot_pdf']['name'],$saved['size'],$saved['hash']);
                                        update_post_meta($otqr_id,self::META_MODELO,$modelo);
                                        update_post_meta($otqr_id,self::META_CLIENTE,$cliente);
                                        update_post_meta($otqr_id,self::META_BOX_ID,$box_id);
                                        delete_post_meta($otqr_id,self::META_ATTACHMENT_ID);
                                        if ($old_private && $old_private!==$saved['path'] && file_exists($old_private)) @unlink($old_private);
                                        $msg='OT subida correctamente.';
                                        $token=self::ensure_public_token($otqr_id);
                                        $cover_url=home_url('/otqr/cover/'.$token.'/');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        ob_start(); ?>
        <div class="card">
            <h1>Subir OT</h1>
            <p><strong>Solo sube el PDF.</strong> Debe llamarse así: <code>OT 19230, MF3307, GARCES -MELIPILLA.pdf</code></p>
            <?php if ($err): ?><p class="error"><?php echo esc_html($err); ?></p><?php endif; ?>
            <?php if ($msg): ?><p class="ok"><?php echo esc_html($msg); ?></p><?php endif; ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($form_action); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_val); ?>" />
                <div class="row"><div style="width:100%">
                    <label for="box_id">BOX asignado al QR</label>
                    <select id="box_id" name="box_id" required style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--border);font-size:16px">
                        <option value="">Seleccionar BOX</option>
                        <?php foreach($active_boxes as $b): ?><option value="<?php echo esc_attr($b['id']); ?>"><?php echo esc_html($b['name']); ?></option><?php endforeach; ?>
                        <option value="manual">Escribir manualmente</option>
                    </select>
                    <div id="box_manual_wrap" style="display:none;margin-top:8px;">
                        <label for="box_manual">Nombre del BOX</label>
                        <input id="box_manual" name="box_manual" type="text" minlength="2" maxlength="80" style="width:100%;padding:12px;border-radius:10px;border:1px solid var(--border);font-size:16px" />
                    </div>
                    <label for="ot_pdf">PDF OT</label>
                    <input id="ot_pdf" name="ot_pdf" type="file" accept="application/pdf" required />
                    <div class="small" style="margin-top:6px;">
                        <label style="display:flex;gap:8px;align-items:center;">
                            <input type="checkbox" name="delete_old" value="1" style="width:auto;padding:0;margin:0;" />
                            Eliminar PDF anterior si existe (opcional)
                        </label>
                    </div>
                </div></div>

                <div class="row">
                    <button type="submit" class="btn primary">Subir</button>
                    <a class="btn" href="<?php echo esc_url($manage_url); ?>">Gestionar</a>
                </div>
            </form>
            <script>
            (function(){
                var sel=document.getElementById('box_id');
                var wrap=document.getElementById('box_manual_wrap');
                var input=document.getElementById('box_manual');
                if(!sel||!wrap||!input){return;}
                function sync(){
                    var manual=sel.value==='manual';
                    wrap.style.display=manual?'block':'none';
                    input.required=manual;
                    if(!manual){input.value='';}
                }
                sel.addEventListener('change',sync);
                sync();
            })();
            </script>

            <?php if ($cover_url): ?>
                <div class="result">
                    <div><strong>Carátula:</strong> <a href="<?php echo esc_url($cover_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($cover_url); ?></a></div>
                    <p class="small">Ctrl+P → Guardar como PDF.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php self::send_minimal_html($title, ob_get_clean()); exit;
    }

    private static function handle_frontend_manage(){
        $title='Gestionar OTs'; $err=''; $msg='';

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/otqr/manage/')));
            exit;
        }

        if (!current_user_can('manage_options')) {
            self::send_minimal_html($title,'<div class="card"><h1>Gestionar OTs</h1><p class="error">No autorizado.</p></div>');
            exit;
        }

        $nonce_action='otqr_manage_action';
        $nonce_val=wp_create_nonce($nonce_action);

        $ot_edit=isset($_GET['edit'])?self::normalize_ot_number(wp_unslash($_GET['edit'])):'';
        $do=isset($_POST['do'])?sanitize_text_field(wp_unslash($_POST['do'])):'';

        if ($_SERVER['REQUEST_METHOD']==='POST'){
            $nonce=isset($_POST['_wpnonce'])?sanitize_text_field(wp_unslash($_POST['_wpnonce'])):'';
            if (!wp_verify_nonce($nonce,$nonce_action)) $err='Sesión expirada. Recarga la página.';
            else {
                $target=isset($_POST['ot'])?self::normalize_ot_number(wp_unslash($_POST['ot'])):'';
                $post_id=$target?self::get_ot_post_by_number($target):0;
                if (!$target||!$post_id) $err='OT no encontrada.';
                else {
                    if ($do==='save_meta'){
                        $modelo=isset($_POST['modelo'])?self::normalize_text(wp_unslash($_POST['modelo'])):'';
                        $cliente=isset($_POST['cliente'])?self::normalize_text(wp_unslash($_POST['cliente'])):'';
                        $box_id=isset($_POST['box_id'])?sanitize_key(wp_unslash($_POST['box_id'])):'';
                        if ($box_id==='manual') {
                            $box_manual=isset($_POST['box_manual'])?sanitize_text_field(wp_unslash($_POST['box_manual'])):'';
                            $box_manual=self::normalize_text($box_manual);
                            $box_len=function_exists('mb_strlen')?mb_strlen($box_manual,'UTF-8'):strlen($box_manual);
                            if ($box_len<2 || $box_len>80) $err='Nombre del BOX inválido (2 a 80 caracteres).';
                            else $box_id=self::resolve_box_id_from_name($box_manual);
                        } elseif ($box_id!=='') {
                            $box=self::get_box_by_id($box_id);
                            if (!$box || empty($box['active'])) $err='BOX inválido o inactivo.';
                        }
                        if ($err==='') {
                            update_post_meta($post_id,self::META_MODELO,$modelo);
                            update_post_meta($post_id,self::META_CLIENTE,$cliente);
                            if ($box_id==='') delete_post_meta($post_id,self::META_BOX_ID);
                            else update_post_meta($post_id,self::META_BOX_ID,$box_id);
                            $msg='Datos actualizados.'; $ot_edit=$target;
                        }
                    } elseif ($do==='replace_pdf'){
                        if (empty($_FILES['ot_pdf'])||empty($_FILES['ot_pdf']['tmp_name'])||!is_uploaded_file($_FILES['ot_pdf']['tmp_name'])) $err='Debes seleccionar un PDF.';
                        else {
                            $filetype=wp_check_filetype_and_ext($_FILES['ot_pdf']['tmp_name'], $_FILES['ot_pdf']['name']);
                            if ($filetype['ext']!=='pdf') $err='El archivo debe ser PDF.';
                            else {
                                // En reemplazo permitimos nombre no estricto, pero si viene con formato lo usamos:
                                $old_private=self::get_valid_private_pdf_path($post_id);
                                $saved=self::save_uploaded_pdf_privately($_FILES['ot_pdf']);
                                if (!$saved['ok']) $err=$saved['error'];
                                else {
                                    self::set_private_pdf_meta($post_id,$saved['path'],$_FILES['ot_pdf']['name'],$saved['size'],$saved['hash']);
                                    delete_post_meta($post_id,self::META_ATTACHMENT_ID);
                                    if ($old_private && $old_private!==$saved['path'] && file_exists($old_private)) @unlink($old_private);
                                    $msg='PDF actualizado.'; $ot_edit=$target;
                                }
                            }
                        }
                    } elseif ($do==='rename_ot'){
                        $new=isset($_POST['new_ot'])?self::normalize_ot_number(wp_unslash($_POST['new_ot'])):'';
                        if ($new===''||$new===$target) $err='Nuevo N° OT inválido.';
                        elseif (self::get_ot_post_by_number($new)) $err='Ya existe la OT '.$new.'.';
                        else {
                            $res=wp_update_post(['ID'=>$post_id,'post_name'=>$new,'post_title'=>'OT '.$new], true);
                            if (is_wp_error($res)) $err='Error renombrando OT.';
                            else { $msg='OT renombrada: '.$target.' → '.$new; $ot_edit=$new; }
                        }
                    } elseif ($do==='delete_ot'){
                        $delete_attach=isset($_POST['delete_attach'])?sanitize_text_field(wp_unslash($_POST['delete_attach'])):'';
                        $attach_id=intval(get_post_meta($post_id,self::META_ATTACHMENT_ID,true));
                        $private_path=self::get_valid_private_pdf_path($post_id);
                        wp_delete_post($post_id,true);
                        if ($delete_attach==='1' && $attach_id) wp_delete_attachment($attach_id,true);
                        if ($private_path && file_exists($private_path)) @unlink($private_path);
                        $msg='OT eliminada.'; $ot_edit='';
                    }
                }
            }
        }

        $box_filter=isset($_GET['box'])?sanitize_key(wp_unslash($_GET['box'])):'';
        $paged=isset($_GET['p'])?max(1,intval($_GET['p'])):1;
        $per_page=50;
        $query_args=['post_type'=>self::CPT,'post_status'=>'publish','posts_per_page'=>$per_page,'paged'=>$paged,'orderby'=>'date','order'=>'DESC','fields'=>'ids'];
        if ($box_filter==='none') $query_args['meta_query']=['relation'=>'OR',['key'=>self::META_BOX_ID,'compare'=>'NOT EXISTS'],['key'=>self::META_BOX_ID,'value'=>'','compare'=>'=']];
        elseif ($box_filter!=='') $query_args['meta_query']=[['key'=>self::META_BOX_ID,'value'=>$box_filter,'compare'=>'=']];
        $query=new WP_Query($query_args);
        $total_pages=max(1,intval($query->max_num_pages));

        self::ensure_tokens_for_existing_ots();
        $upload_url=add_query_arg(['k'=>self::get_public_key()], home_url('/otqr/upload/'));
        $base_url=home_url('/otqr/manage/');

        ob_start(); ?>
        <div class="grid">
          <div class="card">
            <div class="toolbar">
              <div class="title">
                <h1>OTs subidas</h1>
                <p>Gestión rápida: filtrar, abrir PDF/Carátula y editar.</p>
              </div>
              <div class="row" style="margin-top:0;">
                <a class="btn primary" href="<?php echo esc_url($upload_url); ?>">Subir nueva OT</a>
                <?php if ($box_filter!==''): ?><a class="btn" href="<?php echo esc_url($base_url); ?>">Quitar filtro</a><?php endif; ?>
              </div>
            </div>
            <?php if ($err): ?><p class="error"><?php echo esc_html($err); ?></p><?php endif; ?>
            <?php if ($msg): ?><p class="ok"><?php echo esc_html($msg); ?></p><?php endif; ?>
            <form method="get" class="row"><input type="hidden" name="otqr_manage" value="1"/><label for="box_filter">Filtrar BOX</label><select id="box_filter" name="box"><option value="">Todos</option><option value="none" <?php selected($box_filter,'none'); ?>>Sin asignar</option><?php foreach(self::get_active_boxes() as $b): ?><option value="<?php echo esc_attr($b['id']); ?>" <?php selected($box_filter,$b['id']); ?>><?php echo esc_html($b['name']); ?></option><?php endforeach; ?></select><button class="btn" type="submit">Filtrar</button></form>

            <div class="table-wrap">
            <table><thead><tr>
              <th>OT</th><th>Modelo</th><th>Cliente</th><th>BOX</th><th>PDF</th><th>Carátula</th><th>Fecha</th><th class="actions">Acciones</th>
            </tr></thead><tbody>
            <?php if (empty($query->posts)): ?>
              <tr><td colspan="8" class="small">No hay OTs aún.</td></tr>
            <?php else:
              foreach($query->posts as $pid):
                $num=get_post_field('post_name',$pid);
                $modelo=get_post_meta($pid,self::META_MODELO,true);
                $cliente=get_post_meta($pid,self::META_CLIENTE,true);
                $private_pdf=self::get_valid_private_pdf_path($pid);
                $box_label=self::get_box_label_for_ot($pid);
                $token=self::ensure_public_token($pid);
                $view_url=home_url('/otqr/ver/'.$token.'/');
                $pdf_url=$private_pdf?$view_url:'';
                $cover_url=home_url('/otqr/cover/'.$token.'/');
                $edit_url=add_query_arg(['edit'=>$num], $base_url);
            ?>
              <tr>
                <td><span class="pill"><?php echo esc_html($num); ?></span></td>
                <td><?php echo $modelo?esc_html($modelo):'<span class="small">—</span>'; ?></td>
                <td><?php echo $cliente?esc_html($cliente):'<span class="small">—</span>'; ?></td>
                <td><?php echo esc_html($box_label); ?></td>
                <td><?php echo $pdf_url?'<a href="'.esc_url($pdf_url).'" target="_blank" rel="noopener">Ver</a>':'<span class="small">Sin PDF</span>'; ?></td>
                <td><a href="<?php echo esc_url($cover_url); ?>" target="_blank" rel="noopener">Abrir</a></td>
                <td class="small"><?php echo esc_html(get_the_date('d-m-Y',$pid)); ?></td>
                <td class="actions"><a class="btn" href="<?php echo esc_url($edit_url); ?>">Editar</a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody></table>
            </div>

            <?php if ($total_pages>1):
              $prev_args=['p'=>$paged-1]; if($box_filter!=='') $prev_args['box']=$box_filter;
              $next_args=['p'=>$paged+1]; if($box_filter!=='') $next_args['box']=$box_filter;
              $prev=$paged>1?add_query_arg($prev_args, $base_url):'';
              $next=$paged<$total_pages?add_query_arg($next_args, $base_url):'';
            ?>
              <div class="row" style="margin-top:14px;">
                <?php if($prev): ?><a class="btn" href="<?php echo esc_url($prev); ?>">← Anterior</a><?php endif; ?>
                <span class="small">Página <?php echo intval($paged); ?> de <?php echo intval($total_pages); ?></span>
                <?php if($next): ?><a class="btn" href="<?php echo esc_url($next); ?>">Siguiente →</a><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="card">
            <h1>Editar OT</h1>
            <p>Corrige Modelo/Cliente o reemplaza el PDF.</p>

            <?php if ($ot_edit):
              $pid=self::get_ot_post_by_number($ot_edit);
              if ($pid):
                $private_pdf=self::get_valid_private_pdf_path($pid);
                $token=self::ensure_public_token($pid);
                $view_url=home_url('/otqr/ver/'.$token.'/');
                $pdf_url=$private_pdf?$view_url:'';
                $cover_url=home_url('/otqr/cover/'.$token.'/');
                $modelo=get_post_meta($pid,self::META_MODELO,true);
                $cliente=get_post_meta($pid,self::META_CLIENTE,true);
                $selected_box_id=sanitize_key(get_post_meta($pid,self::META_BOX_ID,true));
            ?>
              <div class="result" style="border-top:none;padding-top:0;">
                <div><strong>OT:</strong> <span class="pill"><?php echo esc_html($ot_edit); ?></span></div>
                <div class="small" style="margin-top:6px;">
                  <?php if($pdf_url): ?>PDF: <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener">ver</a><?php else: ?>PDF: <span class="small">sin PDF</span><?php endif; ?>
                  · Carátula: <a href="<?php echo esc_url($cover_url); ?>" target="_blank" rel="noopener">abrir</a>
                </div>
              </div>

              <h2 class="section-title">Datos</h2>
              <div class="section">
              <form method="post">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_val); ?>"/>
                <input type="hidden" name="ot" value="<?php echo esc_attr($ot_edit); ?>"/>
                <input type="hidden" name="do" value="save_meta"/>

                <label for="modelo_meta">Modelo</label>
                <input id="modelo_meta" name="modelo" value="<?php echo esc_attr($modelo); ?>" placeholder="MF3307"/>

                <div class="row"><div style="width:100%">
                  <label for="cliente_meta">Cliente</label>
                  <input id="cliente_meta" name="cliente" value="<?php echo esc_attr($cliente); ?>" placeholder="GARCES -MELIPILLA"/>
                </div></div>

                <div class="row" style="width:100%">
                  <label for="box_meta">BOX</label>
                  <select id="box_meta" name="box_id"><option value="" <?php selected($selected_box_id,''); ?>>Sin asignar</option><?php foreach(self::get_active_boxes() as $b): ?><option value="<?php echo esc_attr($b['id']); ?>" <?php selected($selected_box_id,$b['id']); ?>><?php echo esc_html($b['name']); ?></option><?php endforeach; ?><option value="manual">Escribir manualmente</option></select>
                  <div id="box_meta_manual_wrap" style="display:none;margin-top:8px;">
                    <label for="box_meta_manual">Nombre del BOX</label>
                    <input id="box_meta_manual" name="box_manual" type="text" minlength="2" maxlength="80" />
                  </div>
                </div>
                <div class="row"><button class="btn primary" type="submit">Guardar datos</button></div>
              </form>
              </div>
              <script>
              (function(){
                var sel=document.getElementById('box_meta');
                var wrap=document.getElementById('box_meta_manual_wrap');
                var input=document.getElementById('box_meta_manual');
                if(!sel||!wrap||!input){return;}
                function sync(){
                  var manual=sel.value==='manual';
                  wrap.style.display=manual?'block':'none';
                  input.required=manual;
                  if(!manual){input.value='';}
                }
                sel.addEventListener('change',sync);
                sync();
              })();
              </script>

              <h2 class="section-title">PDF</h2>
              <div class="section">
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_val); ?>"/>
                <input type="hidden" name="ot" value="<?php echo esc_attr($ot_edit); ?>"/>
                <input type="hidden" name="do" value="replace_pdf"/>

                <label for="pdf_replace">Reemplazar PDF</label>
                <input id="pdf_replace" name="ot_pdf" type="file" accept="application/pdf" required />
                <div class="small" style="margin-top:8px;">
                  <label style="display:flex;gap:8px;align-items:center;">
                    <input type="checkbox" name="delete_old" value="1" style="width:auto;padding:0;margin:0;"/>
                    Eliminar PDF anterior (opcional)
                  </label>
                </div>
                <div class="row"><button class="btn primary" type="submit">Guardar PDF</button></div>
              </form>
              </div>

              <h2 class="section-title">Renombrar OT</h2>
              <div class="section">
              <form method="post">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_val); ?>"/>
                <input type="hidden" name="ot" value="<?php echo esc_attr($ot_edit); ?>"/>
                <input type="hidden" name="do" value="rename_ot"/>

                <label for="new_ot">Cambiar N° OT</label>
                <input id="new_ot" name="new_ot" inputmode="numeric" pattern="[0-9]*" placeholder="Nuevo número"/>
                <div class="row"><button class="btn" type="submit">Renombrar</button></div>
              </form>
              </div>

              <h2 class="section-title">Eliminar OT</h2>
              <div class="section">
              <form method="post" onsubmit="return confirm('¿Eliminar OT <?php echo esc_js($ot_edit); ?>?');">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_val); ?>"/>
                <input type="hidden" name="ot" value="<?php echo esc_attr($ot_edit); ?>"/>
                <input type="hidden" name="do" value="delete_ot"/>

                <div class="small" style="margin-bottom:8px;">
                  <label style="display:flex;gap:8px;align-items:center;">
                    <input type="checkbox" name="delete_attach" value="1" style="width:auto;padding:0;margin:0;"/>
                    También eliminar el PDF en Media (opcional)
                  </label>
                </div>

                <button class="btn danger" type="submit">Eliminar OT</button>
              </form>
              </div>
            <?php else: ?><p class="error">OT no encontrada.</p><?php endif; endif; ?>
          </div>
        </div>
        <?php self::send_minimal_html($title, ob_get_clean()); exit;
    }

    public static function handle_public_routes(){
        if (get_query_var('otqr_upload')) self::handle_frontend_upload();
        if (get_query_var('otqr_manage')) self::handle_frontend_manage();
        if (get_query_var('otqr_home')) {
            status_header(404);
            exit;
        }

        $token=get_query_var('otqr_token');
        if (get_query_var('otqr_token_view') && $token){
            $token=sanitize_text_field($token);
            $post_id=self::get_ot_post_by_token($token);
            if (!$post_id){ status_header(404); exit; }
            $path=self::get_valid_private_pdf_path($post_id);
            if ($path===''){ status_header(404); exit; }
            $filename=self::sanitize_pdf_filename(get_post_meta($post_id,self::META_PDF_ORIGINAL_NAME,true));
            $size=@filesize($path);
            if (!$size || $size<1){ status_header(404); exit; }
            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.str_replace('\"','',$filename).'"');
            header('Content-Length: '.intval($size));
            header('X-Robots-Tag: noindex, nofollow', true);
            readfile($path);
            exit;
        }

        if (get_query_var('otqr_token_cover') && $token){
            $token=sanitize_text_field($token);
            $post_id=self::get_ot_post_by_token($token);
            if (!$post_id){ status_header(404); exit; }
            $num=get_post_field('post_name',$post_id);
            $public_url=home_url('/otqr/ver/'.$token.'/');
            $qr_img='https://api.qrserver.com/v1/create-qr-code/?size=600x600&data='.rawurlencode($public_url);
            $modelo=get_post_meta($post_id,self::META_MODELO,true);
            $cliente=get_post_meta($post_id,self::META_CLIENTE,true);
            $box_label=self::get_box_label_for_ot($post_id);
            nocache_headers(); header('Content-Type: text/html; charset=UTF-8'); header('X-Robots-Tag: noindex, nofollow', true);
            $title='OT '.$num.' - Carátula QR';
            ?>
            <!doctype html>
            <html lang="es"><head>
              <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
              <title><?php echo esc_html($title); ?></title>
              <style>
                @page { size: Letter; margin: 0; }
                html, body { height: 100%; }
                body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #fff; color: #000; }
                .sheet { width: 8.5in; height: 11in; margin: 0 auto; display: flex; flex-direction: column; align-items: center; }
                .qr { margin-top: 0.6in; width: 6.0in; height: 6.0in; }
                .label { margin-top: 0.25in; font-size: 18px; letter-spacing: 0.3px; }
                .ot { margin-top: 0.22in; font-size: 22px; }
                .ot strong { font-weight: 800; }
                .meta { margin-top: 0.18in; font-size: 14px; text-align:center; max-width: 7in; }
                .meta .k { font-weight: 700; }
                .toolbar { position: fixed; top: 12px; right: 12px; display: flex; gap: 8px; z-index: 999; }
                .btn { appearance: none; border: 1px solid #ddd; background: #fff; padding: 8px 10px; border-radius: 8px; cursor: pointer; font-size: 12px; }
                .btn:hover { border-color: #bbb; }
                @media print { .toolbar { display: none !important; } }
              </style>
            </head><body>
              <div class="toolbar"><button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button></div>
              <div class="sheet">
                <img class="qr" src="<?php echo esc_url($qr_img); ?>" alt="QR" />
                <div class="label">ESCANEA TU ORDEN DE TRABAJO</div>
                <div class="ot">OT <strong><?php echo esc_html($num); ?></strong></div>
                <?php if ($modelo || $cliente || $box_label): ?>
                  <div class="meta">
                    <?php if ($modelo): ?><div><span class="k">MODELO:</span> <?php echo esc_html($modelo); ?></div><?php endif; ?>
                    <?php if ($cliente): ?><div><span class="k">CLIENTE:</span> <?php echo esc_html($cliente); ?></div><?php endif; ?>
                    <div><span class="k">BOX:</span> <?php echo esc_html($box_label); ?></div>
                  </div>
                <?php endif; ?>
              </div>
            </body></html><?php
            exit;
        }

        if (get_query_var('otqr_cover') || is_singular(self::CPT)) {
            status_header(404);
            exit;
        }
    }
}
OTQR_Automator::init();
