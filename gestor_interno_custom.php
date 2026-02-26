<?php
/*
Plugin Name: SaaS Gerenciador de Conteúdo - Versão Final Corrigida
Description: Sistema SaaS completo: Login, Dashboard Frontend, Gestão de CPTs e Bloqueio WP-Admin.
Version: 2.0
Author: Rosiane C S Passos
*/

if (!defined('ABSPATH')) exit;

/**
 * 1. CONFIGURAÇÃO NA ATIVAÇÃO
 */
register_activation_hook(__FILE__, function() {
    add_role('usuario_convidado', 'Usuário Convidado', [
        'read' => true,
        'upload_files' => true,
        'edit_posts' => true,
    ]);
    flush_rewrite_rules(); // Limpa links quebrados
});

/**
 * 2. BLOQUEIO DO WP-ADMIN PARA CONVIDADOS
 */
add_action('admin_init', function() {
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (current_user_can('usuario_convidado') && !current_user_can('administrator')) {
        wp_redirect(home_url('/dashboard-saas'));
        exit;
    }
});

/**
 * 3. REGISTRO DE CONTEÚDOS (PROJETOS, CURSOS, ETC)
 */
add_action('init', function() {
    $tipos = [
        'projeto'     => 'Projetos',
        'curso'       => 'Cursos',
        'experiencia' => 'Experiências',
        'competencia' => 'Competências'
    ];
    foreach ($tipos as $slug => $nome) {
        register_post_type($slug, [
            'public' => true,
            'label'  => $nome,
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true
        ]);
    }
});

/**
 * 4. SHORTCODE DE LOGIN [sistema_login]
 */
add_shortcode('sistema_login', function() {
    if (is_user_logged_in()) {
        return '<script>window.location.href="'.home_url('/dashboard-saas').'";</script>
                <div style="text-align:center; padding:20px;">Redirecionando para o painel...</div>';
    }
    
    ob_start(); ?>
    <div class="saas-login-box" style="max-width:400px; margin:40px auto; padding:30px; border:1px solid #eee; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
        <h2 style="text-align:center; margin-bottom:20px;">Acesso ao Sistema</h2>
        <?php wp_login_form(['redirect' => home_url('/dashboard-saas')]); ?>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * 5. SHORTCODE DO PAINEL [saas_dashboard]
 */
add_shortcode('saas_dashboard', function() {
    if (!is_user_logged_in()) {
        return '<p style="text-align:center; padding:50px;">Acesso negado. Por favor, <a href="'.home_url('/login').'">faça login</a>.</p>';
    }

    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
    $is_admin = current_user_can('administrator');

    ob_start(); ?>
    <style>
        .saas-wrapper { display: flex; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; font-family: sans-serif; min-height: 600px; background: #fff; }
        .saas-sidebar { width: 240px; background: #2c3e50; color: #fff; padding: 20px; }
        .saas-sidebar a { color: #ecf0f1; text-decoration: none; display: block; padding: 12px; border-radius: 5px; margin-bottom: 5px; transition: 0.2s; }
        .saas-sidebar a:hover, .saas-sidebar a.active { background: #34495e; color: #3498db; }
        .saas-main { flex: 1; padding: 40px; }
        .form-item { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .form-item label { font-weight: bold; display: block; margin-bottom: 8px; }
        .form-item input[type="text"], .form-item textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-update { background: #3498db; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        .btn-update:hover { background: #2980b9; }
    </style>

    <div class="saas-wrapper">
        <div class="saas-sidebar">
            <h3 style="font-size: 18px; margin-bottom: 20px; color: #3498db;">PAINEL SAAS</h3>
            <a href="?tab=home" class="<?php echo $tab=='home'?'active':''; ?>">🏠 Home / Cabeçalho</a>
            <a href="?tab=portifolio" class="<?php echo $tab=='portifolio'?'active':''; ?>">💼 Portfólio</a>
            <a href="?tab=conteudo" class="<?php echo $tab=='conteudo'?'active':''; ?>">📝 Conteúdo e Cursos</a>
            <a href="?tab=rodape" class="<?php echo $tab=='rodape'?'active':''; ?>">⬇️ Rodapé e Redes</a>
            <?php if($is_admin): ?>
                <a href="?tab=users" style="color: #f1c40f;">👥 Gestão de Usuários</a>
            <?php endif; ?>
            <a href="<?php echo wp_logout_url(home_url()); ?>" style="margin-top: 40px; color: #e74c3c;">🚪 Sair</a>
        </div>
        <div class="saas-main">
            <?php
            switch($tab) {
                case 'users': 
                    if($is_admin) saas_logic_users(); 
                    break;
                case 'portifolio': 
                    saas_logic_posts('projeto', 'Meus Projetos'); 
                    break;
                case 'conteudo':
                    saas_logic_posts('curso', 'Meus Cursos');
                    saas_logic_posts('experiencia', 'Experiências');
                    break;
                case 'rodape':
                    saas_render_input('social_fb', 'Link Facebook');
                    saas_render_input('social_ig', 'Link Instagram');
                    saas_render_input('mgp_rodape_txt', 'Texto do Rodapé (Copyright)', true);
                    break;
                default:
                    saas_render_input('home_capa_txt', 'Título da Capa (Home)');
                    saas_render_input('home_capa_img', 'URL da Imagem de Capa');
                    break;
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * 6. FUNÇÕES DE LÓGICA E RENDERIZAÇÃO
 */
function saas_render_input($opt, $label, $textarea = false) {
    if (isset($_POST['save_'.$opt])) {
        update_option($opt, current_user_can('administrator') ? $_POST['val'] : sanitize_text_field($_POST['val']));
        echo '<div style="color:green; margin-bottom:10px;">✅ Atualizado!</div>';
    }
    $curr = get_option($opt);
    echo "<form method='post' class='form-item'><label>$label</label>";
    if ($textarea) echo "<textarea name='val' rows='4'>".esc_textarea($curr)."</textarea>";
    else echo "<input type='text' name='val' value='".esc_attr($curr)."'>";
    echo "<br><button type='submit' name='save_$opt' class='btn-update' style='margin-top:10px;'>Salvar Alteração</button></form>";
}

function saas_logic_posts($type, $title) {
    if (isset($_POST['add_'.$type])) {
        wp_insert_post(['post_type' => $type, 'post_title' => sanitize_text_field($_POST['t']), 'post_status' => 'publish']);
    }
    if (isset($_GET['del'])) {
        wp_delete_post($_GET['del']);
        echo '<script>window.location.href="?tab='.$_GET['tab'].'";</script>';
    }
    $posts = get_posts(['post_type' => $type, 'numberposts' => -1]);
    echo "<h3>$title</h3>";
    echo "<ul style='background:#f9f9f9; padding:15px; border-radius:5px;'>";
    foreach($posts as $p) {
        echo "<li style='display:flex; justify-content:space-between; margin-bottom:10px;'>{$p->post_title} <a href='?tab={$_GET['tab']}&del={$p->ID}' style='color:red;'>[Excluir]</a></li>";
    }
    echo "</ul><form method='post'><input type='text' name='t' required placeholder='Novo item...'> <button type='submit' name='add_$type' class='btn-update'>Adicionar</button></form><hr>";
}

function saas_logic_users() {
    if (isset($_POST['create_u'])) {
        $id = wp_create_user(sanitize_user($_POST['u']), $_POST['p'], sanitize_email($_POST['e']));
        if(!is_wp_error($id)) wp_update_user(['ID' => $id, 'role' => $_POST['r']]);
    }
    $users = get_users(['role__in' => ['administrator', 'usuario_convidado']]);
    echo "<h3>Gerenciar Usuários</h3>";
    foreach($users as $u) echo "<div>• {$u->user_login} ({$u->roles[0]})</div>";
    echo "<hr><h4>Cadastrar Novo</h4><form method='post' style='display:grid; gap:10px; max-width:300px;'>
    <input name='u' placeholder='Usuário' required><input name='e' placeholder='Email' required><input name='p' placeholder='Senha' required>
    <select name='r'><option value='usuario_convidado'>Convidado</option><option value='administrator'>Admin</option></select>
    <button name='create_u' class='btn-update'>Criar</button></form>";
}

/**
 * 7. SHORTCODES DE EXIBIÇÃO PARA O ELEMENTOR
 */
add_shortcode('saas_home_capa', function() { return get_option('home_capa_txt'); });
add_shortcode('saas_texto_rodape', function() { return get_option('mgp_rodape_txt'); });
add_shortcode('saas_redes_sociais', function() {
    $fb = get_option('social_fb'); $ig = get_option('social_ig');
    return "<div class='social-wrap'>
            ".($fb ? "<a href='".esc_url($fb)."'>Facebook</a> " : "")."
            ".($ig ? "<a href='".esc_url($ig)."'>Instagram</a>" : "")."
            </div>";
});