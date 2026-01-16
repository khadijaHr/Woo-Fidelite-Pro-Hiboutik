<?php
/**
 * Plugin Name:         Fidélité Pro — Points & Récompenses Hiboutik
 * Description:         Système complet de gestion des points de fidélité pour WooCommerce, synchronisé avec Hiboutik. Permet aux clients de gagner des points sur leurs achats, de les consulter dans leur compte, et de les utiliser comme réduction ou pour obtenir des produits gratuits. Synchronisation automatique des clients et des commandes, historique détaillé, et intégration complète avec l'API Hiboutik.
 * Version:           	1.3.6
 * Requires at least: 	6.2
 * Requires PHP:      	7.0
 * Author:            	Khadija Har
 * Author URI:        	https://github.com/khadijahr/
 * License:           	GPL v3 or later
 * License URI:       	https://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

// Création de la table en activant le plugin
function lp_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'loyalty_points';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wp_user_id BIGINT UNSIGNED NULL,
        hiboutik_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(50) NOT NULL,
        points INT DEFAULT 0
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'lp_create_table');

function lp_add_admin_page() {
    add_menu_page(
        'Points de Fidélité',
        'Fidélité Clients',
        'manage_options',
        'loyalty-points',
        'lp_display_customers',
        'dashicons-awards',
        20
    );

    // Ajouter une sous-page pour les logs des commandes
    add_submenu_page(
        null, // Page non visible directement dans le menu
        'Historique des Commandes',
        'Logs',
        'manage_options',
        'loyalty-orders',
        'lp_display_orders'
    );
    
}
add_action('admin_menu', 'lp_add_admin_page');


// Fonction pour appeler l'API Hiboutik
function lp_call_api($endpoint) {    
    $hiboutik_account = get_option('hiboutik_account');

    $baseUrl = "https://$hiboutik_account.hiboutik.com/api/";
    $url = $baseUrl . $endpoint;

    $username = get_option('hiboutik_user');
    $password = get_option('hiboutik_key');

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password"),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'
        ]
    ]);

    if (is_wp_error($response)) {
        return [];        
    }    

    return json_decode(wp_remote_retrieve_body($response), true);
}

//
function lp_call_api_put($endpoint, $body) {
    $hiboutik_account = get_option('hiboutik_account');

    $baseUrl = "https://$hiboutik_account.hiboutik.com/api/";
    $url = $baseUrl . $endpoint;

    $username = get_option('hiboutik_user');
    $password = get_option('hiboutik_key');

    $response = wp_remote_request($url, [
        'method'    => 'PUT',
        'headers'   => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password"),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json'
        ],
        'body'      => json_encode($body)
    ]);

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}


// Fonction pour normaliser un numéro de téléphone (enlever espaces, tirets et indicatifs)
function lp_normalize_phone($phone) {
    // Supprimer tous les espaces, tirets et parenthèses
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Supprimer les indicatifs courants (exemple : +212, 00212 pour le Maroc)
    $phone = preg_replace('/^(\+212|00212)/', '', $phone);

    // Ajouter "0" au début si ce n'est pas déjà fait
    if (!preg_match('/^0/', $phone)) {
        $phone = '0' . $phone;
    }
    
    // Vérifier si c'est un numéro valide (garder uniquement les chiffres)
    return preg_replace('/[^0-9]/', '', $phone);
}


// Ajouter le bouton Synchroniser dans la page d'administration
// function lp_display_customers() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'loyalty_points';

//     // Récupérer le terme de recherche
//     $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

//     // Si une recherche est faite, filtre la requête
//     if (!empty($search_query)) {
//         $results = $wpdb->get_results($wpdb->prepare(
//             "SELECT * FROM $table_name WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s",
//             "%{$search_query}%", "%{$search_query}%", "%{$search_query}%"
//         ));
//     } else {
//         $results = $wpdb->get_results("SELECT * FROM $table_name");
//     }

//     echo '<div class="wrap">';
//     echo '<h2>Liste des Clients et Points de Fidélité</h2>';

//     // Formulaire de recherche
//     echo '<form method="get">
//             <input type="hidden" name="page" value="loyalty-points">
//             <input type="search" name="s" value="' . esc_attr($search_query) . '" placeholder="Rechercher un client..." style="width: 300px; padding: 5px;">
//             <input type="submit" class="button button-secondary" value="Rechercher">
//           </form><br>';

//     // Bouton Synchroniser
//     echo '<form method="post">
//             <input type="submit" name="lp_sync_customers" class="button button-primary" value="Synchroniser les Clients">
//             </form><br>';

//     if (!empty($results)) {
//         echo '<table class="widefat fixed">
//                 <thead>
//                     <tr>
//                         <th>Nom</th>
//                         <th>Email</th>
//                         <th>Téléphone</th>
//                         <th>Points de fidélité</th>
//                         <th>Compte WordPress</th>
//                         <th>Log</th>
//                         <th>Modifier</th>
//                     </tr>
//                 </thead>
//                 <tbody>';

//         foreach ($results as $row) {
//             $wp_user_link = $row->wp_user_id ? '<a href="' . get_edit_user_link($row->wp_user_id) . '">Voir compte</a>' : 'Non trouvé';
//             $log_link = '<a href="?page=loyalty-orders&customer_id=' . $row->id . '">Voir Log</a>';
//             $edit_link = '<a href="?page=edit-loyalty-points&customer_id=' . $row->id . '" class="button">Edit</a>';

//             echo "<tr>
//                     <td>{$row->name}</td>
//                     <td>{$row->email}</td>
//                     <td>{$row->phone}</td>
//                     <td>{$row->points}</td>
//                     <td>{$wp_user_link}</td>
//                     <td>{$log_link}</td>
//                     <td>{$edit_link}</td>
//                   </tr>";
//         }

//         echo '</tbody></table>';
//     } else {
//         echo '<p>Aucun client trouvé.</p>';
//     }

//     echo '</div>';
// }

add_action('admin_enqueue_scripts', 'lp_enqueue_select2_assets');
function lp_enqueue_select2_assets($hook) {
    // Charger les styles pour les pages du plugin
    if ($hook === 'toplevel_page_loyalty-points' || $hook === 'loyalty-points_page_loyalty-orders') {
        // Charger le CSS du plugin
        wp_enqueue_style(
            'loyalty-points-admin-css',
            plugin_dir_url(__FILE__) . 'assets/css/loyalty-points-admin.css',
            array(),
            '1.3.6'
        );
    }

    if ($hook !== 'toplevel_page_loyalty-points') {
        return;
    }

    // Ajouter les styles et scripts Select2
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
}

function lp_display_customers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'loyalty_points';

    // Si le bouton a été cliqué, lancer la synchronisation
    if (isset($_POST['lp_sync_customers'])) {
        lp_sync_customers();
        echo '<div class="updated"><p>Les clients ont été synchronisés avec succès !</p></div>';
    }

    // Définir le nombre d'éléments par page
    $items_per_page = 10;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $items_per_page;

    // Récupérer le terme de recherche
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Construire la requête SQL
    $where_clause = '';
    $query_params = [];

    // if (!empty($search_query)) {
    //     $where_clause = "WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s";
    //     $query_params = ["%{$search_query}%", "%{$search_query}%", "%{$search_query}%"];
    // }

    if (!empty($search_query)) {
        $where_clause = "WHERE id = %d";
        $query_params = [$search_query];
    }
    
    // Récupérer le nombre total d'éléments
    if (!empty($query_params)) {
        $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name $where_clause", ...$query_params));
    } else {
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    // Récupérer les résultats avec pagination
    $query = "SELECT * FROM $table_name $where_clause LIMIT %d OFFSET %d";
    
    // Exécuter la requête avec les paramètres appropriés
    if (!empty($query_params)) {
        $query_params[] = $items_per_page;
        $query_params[] = $offset;
        $results = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
    } else {
        $results = $wpdb->get_results($wpdb->prepare($query, $items_per_page, $offset));
    }


    echo '<div class="wrap">';
    echo '<h2>Liste des Clients et Points de Fidélité</h2>';

    echo '<div class="divflex">';
    // Formulaire de recherche    
    echo '<div><form method="get" class="search-form" style="display: flex; align-items: center; gap: 8px;">
        <input type="hidden" name="page" value="loyalty-points">
        <select name="s" style="width: 300px; padding: 3px 8px;">
            <option value="">Sélectionner un client...</option>';
    
    // Récupérer tous les clients pour le menu déroulant
    $clients = $wpdb->get_results("SELECT id, name, email FROM $table_name ORDER BY name ASC");

    foreach ($clients as $client) {
        $selected = ($search_query == $client->id) ? 'selected' : '';
		echo "<option value='{$client->id}' $selected>{$client->name} - {$client->email}</option>";
    }

    echo '</select>
            <input type="submit" class="button button-secondary" value="Rechercher">
        </form></div>';


    // Bouton Synchroniser
    echo '<div><form method="post">
            <input type="submit" name="lp_sync_customers" class="button button-primary" value="Synchroniser les Clients">
            </form></div>';
    echo '</div>';

    if (!empty($results)) {
        echo '<table class="widefat fixed">
                <thead>
                    <tr>
                        <th style="width: 16%;">Nom</th>
                        <th style="width: 18%;">Email</th>
                        <th>Téléphone</th>
                        <th>Points de fidélité</th>
                        <th>Compte WordPress</th>
                        <th>Log</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $row) {
            $wp_user_link = $row->wp_user_id ? '<a href="' . get_edit_user_link($row->wp_user_id) . '">Voir compte</a>' : 'Non trouvé';
            $log_link = '<a href="?page=loyalty-orders&customer_id=' . $row->id . '">Voir Log</a>';
           
            echo "<tr>
                    <td>{$row->name}</td>
                    <td>{$row->email}</td>
                    <td>{$row->phone}</td>
                    <td>{$row->points}</td>
                    <td>{$wp_user_link}</td>
                    <td>{$log_link}</td>
                  </tr>";
        }

        echo '</tbody></table>';

        // Afficher la pagination
        $total_pages = ceil($total_items / $items_per_page);

        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'   => $total_pages,
                'current' => $paged,
            ]);
            echo '</div></div>';
        }
    } else {
        echo '<p>Aucun client trouvé.</p>';
    }

    echo '</div>';

    echo '<script>
        jQuery(document).ready(function($) {
            $("select[name=\'s\']").select2({
                placeholder: "Sélectionner un client",
                allowClear: true,
                width: "resolve"
            });
        });
        </script>';
}


// Fonction pour synchroniser les clients et mettre à jour les points
// function lp_sync_customers() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'loyalty_points';

//     $customers = lp_call_api("customers");

//     if (empty($customers)) {
//         return;
//     }

//     // Récupérer les utilisateurs WordPress avec leur téléphone
//     $users = get_users();
//     $wp_users_by_phone = [];

//     foreach ($users as $user) {
//         $user_phone = get_user_meta($user->ID, 'phone_number', true);
//         if ($user_phone) {
//             $normalized_phone = lp_normalize_phone($user_phone);
//             $wp_users_by_phone[$normalized_phone] = $user->ID;
//         }
//     }

//     foreach ($customers as $customer) {
//         $name = sanitize_text_field($customer['first_name'] . ' ' . $customer['last_name']);
//         $email = sanitize_email($customer['email']);
//         $phone = sanitize_text_field($customer['phone']);
//         $points = intval($customer['loyalty_points']);
//         $hiboutik_id = intval($customer['customers_id']);

//         // Normaliser le téléphone de l'API
//         $normalized_phone = lp_normalize_phone($phone);

//         // Trouver un utilisateur WordPress avec ce numéro
//         $wp_user_id = $wp_users_by_phone[$normalized_phone] ?? null;

//         // Vérifier si le client existe déjà
//         $existing_customer = $wpdb->get_row($wpdb->prepare(
//             "SELECT id, points FROM $table_name WHERE phone = %s",
//             $normalized_phone
//         ));

//         if ($existing_customer) {
//             // Mettre à jour les points de fidélité si le client existe déjà
//             $wpdb->update(
//                 $table_name,
//                 ['points' => $points],
//                 ['id' => $existing_customer->id],
//                 ['%d'],
//                 ['%d']
//             );
//         } else {
//             // Insérer un nouveau client s'il n'existe pas
//             $wpdb->insert(
//                 $table_name,
//                 [
//                     'wp_user_id' => $wp_user_id,
//                     'name'   => !empty($name) ? $name : ' ',
//                     'email'  => !empty($email) ? $email : ' ',
//                     'phone'  => !empty($normalized_phone) ? $normalized_phone : '0000000000',
//                     'points' => $points,
//                     'hiboutik_id' => $hiboutik_id,                    
//                 ],
//                 ['%d', '%s', '%s', '%s', '%d' , '%d']
//             );
//         }
//     }
// }

/**
 * Normalise et mappe les téléphones WP → user-id.
 *
 * @return array [ phone_normalisé => user_id ]
 */
function lp_get_wp_users_by_phone(): array {
    global $wpdb;

    // 1. IDs uniquement (léger en mémoire)
    $user_ids = get_users( [
        'fields' => 'ID',
        'number' => -1,
    ] );

    if ( empty( $user_ids ) ) {
        return [];
    }

    // 2. Une seule requête pour les métas phone_number
    $placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
    $meta_key     = 'phone_number';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT user_id, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s
              AND user_id IN ($placeholders)
            ",
            array_merge( [ $meta_key ], $user_ids )
        )
    );

    // 3. Mapping [phone_normalisé => user_id]
    $map = [];
    foreach ( $rows as $row ) {
        if ( ! empty( $row->meta_value ) ) {
            $map[ lp_normalize_phone( $row->meta_value ) ] = (int) $row->user_id;
        }
    }

    return $map;
}

function lp_sync_customers() {
    global $wpdb;
    $table = $wpdb->prefix . 'loyalty_points';

    // --- 1. Données Hiboutik --------------------------------------------------
    $customers = lp_call_api( 'customers' );
    if ( empty( $customers ) ) {
        return;
    }

    // --- 2. Mapping WP users par téléphone ------------------------------------
    $wp_users_by_phone = lp_get_wp_users_by_phone();

    // --- 3. Boucle de synchronisation -----------------------------------------
    foreach ( $customers as $c ) {

        $name   = sanitize_text_field( trim( "{$c['first_name']} {$c['last_name']}" ) );
        $email  = sanitize_email( $c['email'] );
        $phone  = sanitize_text_field( $c['phone'] );
        $points = (int) $c['loyalty_points'];
        $hib_id = (int) $c['customers_id'];

        // Téléphone normalisé
        $phone_norm = lp_normalize_phone( $phone );

        // User WP éventuel
        $wp_user_id = $wp_users_by_phone[ $phone_norm ] ?? null;

        // Existe déjà ?
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, points FROM $table WHERE phone = %s",
                $phone_norm
            )
        );

        if ( $existing ) {
            // ▼ Mise à jour des points
            $wpdb->update(
                $table,
                [ 'points' => $points ],
                [ 'id'     => (int) $existing->id ],
                [ '%d' ],
                [ '%d' ]
            );
        } else {
            // ▼ Insertion
            $wpdb->insert(
                $table,
                [
                    'wp_user_id'  => $wp_user_id,
                    'name'        => $name ?: ' ',
                    'email'       => $email ?: ' ',
                    'phone'       => $phone_norm ?: '0000000000',
                    'points'      => $points,
                    'hiboutik_id' => $hib_id,
                ],
                [ '%d', '%s', '%s', '%s', '%d', '%d' ]
            );
        }
    }
}


//
function lp_create_orders_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'loyalty_details';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        loyalty_id INT NOT NULL,   
        sale_id_hiboutik VARCHAR(50) NOT NULL,     
        raison_detail VARCHAR(255) NOT NULL,
        order_id VARCHAR(50) NULL,        
        points_earned INT DEFAULT 0,
        points_redeemed INT DEFAULT 0,
        points_total INT DEFAULT 0,
        date_log DATETIME NOT NULL,
        FOREIGN KEY (loyalty_id) REFERENCES {$wpdb->prefix}loyalty_points(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'lp_create_orders_table');

function lp_sync_orders($customer_id = null) {
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'loyalty_points';
    $orders_table = $wpdb->prefix . 'loyalty_details';

    // Récupérer les clients à synchroniser (un seul si $customer_id est fourni)
    $query = "SELECT id, hiboutik_id FROM $loyalty_table";
    if ($customer_id) {
        $query .= $wpdb->prepare(" WHERE id = %d", $customer_id);
    }

    $customers = $wpdb->get_results($query);

    foreach ($customers as $customer) {
        $hiboutik_id = $customer->hiboutik_id;
        $loyalty_id = $customer->id;

        // Récupérer les commandes du client depuis l'API
        $salesUrl = "customer/{$hiboutik_id}/sales/";
        $orders = lp_call_api($salesUrl);

        // Vérifier que la réponse est bien un tableau
        if (!is_array($orders)) {
            error_log("Erreur API: Réponse inattendue pour $salesUrl - " . print_r($orders, true));
            return;
        }

        if (!empty($orders)) {
            foreach ($orders as $order) {
                // Vérifier si l'entrée est un tableau
                if (!is_array($order) || !isset($order['sale_id'])) {
                    error_log("Erreur API: Commande invalide - " . print_r($order, true));
                    continue;
                }

                $saleDetailUrl = "sales/{$order['sale_id']}";
                $order_details = lp_call_api($saleDetailUrl);

                // var_dump($order_details);                               
                          
                // Vérifier que la réponse contient bien des détails exploitables
                if (!is_array($order_details) || empty($order_details[0]) || !is_array($order_details[0])) {
                    error_log("Erreur API: Détails de commande non valides pour $saleDetailUrl - " . print_r($order_details, true));
                    continue;
                }
               
                if (!empty($order_details)) {
                    $sale = $order_details[0];

                    // Vérifier si les clés nécessaires existent
                    if (!isset($sale['points'], $sale['customer_id'])) {
                        error_log("Erreur API: Détails de vente incomplets - " . print_r($sale, true));
                        continue;
                    }

                    // Vérifier si la commande est validée
                    $status = ($order['completed_at'] != "0000-00-00 00:00:00") ? "validée" : "en cours";
                    if ($status !== "validée") {
                        continue;
                    }

                    // Extraire l'ID WooCommerce si présent
                    $order_woo = !empty($sale['comments']) && preg_match('/order_id\s*:\s*(\d+)/', $sale['comments'], $matches)
                        ? htmlspecialchars($matches[1])
                        : '';
                    
                    // Vérifier si la commande est déjà enregistrée
                    $existing_order = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $orders_table WHERE sale_id_hiboutik = %s",
                        $order['sale_id']
                    ));
					
					$loyality_points = $wpdb->get_var( $wpdb->prepare( 
						"SELECT points_total FROM $orders_table WHERE loyalty_id = %s ORDER BY order_id ASC LIMIT 1",
						$loyalty_id
					));

                    if (empty($order_woo)) {
                        $raison_detail = "Points gagnés avec la commande sur Hiboutik";                        
                    }
                    else {
                        $raison_detail = "Points gagnés avec la commande #<a href='" . admin_url("post.php?post={$order_woo}&action=edit") . "' target='_blank'>{$order_woo}</a>";
                    }
                        
                    if (!$existing_order) {
                        // Insérer la commande dans la table
                        $wpdb->insert(
                            $orders_table,
                            [
                                'loyalty_id'       => $loyalty_id,
                                'sale_id_hiboutik' => $order['sale_id'],
                                'raison_detail'    => $raison_detail,
                                'order_id'         => $order_woo,                                
                                'points_earned'    => $sale['points'],
                                'points_redeemed'  => 0,
                                'points_total'     => $sale['points'] + $loyality_points,
                                'date_log'         => $sale['created_at'],                       
                            ],
                            ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
                        );
                    }
                }
            }
        }
    }
}

// 🔁 Planifie l'événement s'il n'est pas encore programmé
if ( ! wp_next_scheduled( 'lp_sync_orders_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'lp_sync_orders_event' );
}

// 🕐 Exécute lp_sync_orders() à chaque fois que l'événement est déclenché
add_action( 'lp_sync_orders_event', 'lp_sync_orders' );

//
function lp_display_orders() {
    global $wpdb;
    $table_name_orders = $wpdb->prefix . 'loyalty_details';
    $table_name_customers = $wpdb->prefix . 'loyalty_points';

    if (!isset($_GET['customer_id'])) {
        echo '<div class="wrap"><h2>Erreur</h2><p>Client introuvable.</p></div>';
        return;
    }

    $customer_id = intval($_GET['customer_id']);

    // Vérifier si on doit synchroniser les commandes
    if (isset($_POST['lp_sync_orders'])) {
        lp_sync_orders(); // Exécuter la synchronisation
        echo '<div class="updated"><p>Les commandes ont été synchronisées avec succès !</p></div>';
    }

    // Récupérer les informations du client
    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name_customers WHERE id = %d", $customer_id));
   
    if (!$customer) {
        echo '<div class="wrap"><h2>Erreur</h2><p>Client introuvable.</p></div>';
        return;
    }


    echo '<div class="wrap">';
    echo '<h2>Commandes de <span style="color: #2271b1">' . esc_html($customer->name) . '</span></h2>';

    echo '<div class="divflex">';
    
    // Bouton Synchroniser les commandes
    echo '<div><form method="post" style="margin-top: 10px;">
            <input type="submit" name="lp_sync_orders" class="button button-primary" value="Synchroniser les commandes">
          </form></div>';

    echo '<a href="?page=loyalty-points" class="button">Retour</a>';

    echo '</div>';

    // PAGINATION - Récupération de la page actuelle
    $per_page = 6; // Nombre d'éléments par page
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;

    // Récupérer le nombre total de commandes
    $total_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name_orders WHERE loyalty_id = %d", $customer_id));

    // Calcul du nombre total de pages
    $total_pages = ceil($total_orders / $per_page);

    // Récupérer les commandes avec LIMIT et OFFSET
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT lo.*, lp.name 
         FROM $table_name_orders AS lo
         JOIN $table_name_customers AS lp ON lo.loyalty_id = lp.id
         WHERE lo.loyalty_id = %d
         ORDER BY lo.date_log DESC
         LIMIT %d OFFSET %d",
        $customer_id, $per_page, $offset
    ));

    if (!empty($orders)) {
        echo '<table class="widefat fixed" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sale Hiboutik ID</th>
                        <th>Raison detail</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Points Gagnés</th>                        
                        <th>Points Redeemed</th>
                        <th>Points Total</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($orders as $order) {
            echo "<tr>
                    <td>{$order->id}</td>
                    <td>{$order->sale_id_hiboutik}</td>
                    <td>{$order->raison_detail}</td>
                    <td>{$order->name}</td>                     
                    <td>{$order->date_log}</td>                    
                    <td>{$order->points_earned}</td>
                    <td>{$order->points_redeemed}</td>
                    <td>{$order->points_total}</td>
                  </tr>";
        }

        echo '</tbody></table>';

        // PAGINATION - Affichage des liens de navigation
        if ($total_pages > 1) {
            echo '<div class="tablenav">';
            if ($paged > 1) {
                echo '<a class="prev page-numbers" href="?page=loyalty-orders&customer_id=' . $customer_id . '&paged=' . ($paged - 1) . '">&laquo;</a>';
            }

            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $paged) {
                    echo '<span class="page-numbers current">' . $i . '</span>';
                } else {
                    echo '<a class="page-numbers" href="?page=loyalty-orders&customer_id=' . $customer_id . '&paged=' . $i . '">' . $i . '</a>';
                }
            }

            if ($paged < $total_pages) {
                echo '<a class="next page-numbers" href="?page=loyalty-orders&customer_id=' . $customer_id . '&paged=' . ($paged + 1) . '">&raquo;</a>';
            }
            echo '</div>';
        }
    } else {
        echo '<p>Aucune commande trouvée pour ce client.</p>';
    }

    echo '</div>';
}

// Ajouter un champ et un bouton pour utiliser les points dans le panier
function lp_display_loyalty_points() {
    if (is_cart()) {
        global $wpdb;
        $user_id = get_current_user_id();

        // Vérification de l'utilisateur connecté
        if ($user_id) {
            // echo '<p>ID Utilisateur : ' . esc_html($user_id) . '</p>';

            // Récupération des points depuis la table wp_loyalty_points
            $points = $wpdb->get_var($wpdb->prepare(
                "SELECT points FROM {$wpdb->prefix}loyalty_points WHERE wp_user_id = %d",
                $user_id
            ));

            // Si aucun point trouvé, on met 0
            $points = $points !== null ? intval($points) : 0;

            echo '<div class="lp-loyalty-points">
                    <span>Mes points de fidélité : <strong>' . esc_html($points) . '</strong>(' . esc_html($points) . ' MAD)</span>
                  </div>';
            // Afficher le message de réduction si des points sont disponibles
        } else {
            echo '<p>Connectez-vous pour utiliser vos points de fidélité.</p>';
        }
    }
}
add_action('woocommerce_before_cart', 'lp_display_loyalty_points');

// Ajouter un champ et un bouton pour utiliser les points dans le panier
// function lp_display_loyalty_points_field() {
//     if (is_cart()) {
//         global $wpdb;
//         $user_id = get_current_user_id();

//         // Vérification de l'utilisateur connecté
//         if ($user_id) {
//             // echo '<p>ID Utilisateur : ' . esc_html($user_id) . '</p>';

//             // Récupération des points depuis la table wp_loyalty_points
//             $points = $wpdb->get_var($wpdb->prepare(
//                 "SELECT points FROM {$wpdb->prefix}loyalty_points WHERE wp_user_id = %d",
//                 $user_id
//             ));

//             // Si aucun point trouvé, on met 0
//             $points = $points !== null ? intval($points) : 0;

//             echo '<div class="lp-loyalty-points">
//                     <h3>Utiliser mes points de fidélité</h3>
//                     <p>Vous avez <strong>' . esc_html($points) . '</strong> points disponibles.</p>
//                     <input type="number" id="lp_points_to_use" name="lp_points_to_use" min="0" max="' . esc_attr($points) . '" value="0">
//                     <button type="button" id="lp_apply_points" class="button">Appliquer les points</button>
//                   </div>';
            
//             echo '<script>
//                 jQuery(document).ready(function($) {
//                     $("#lp_apply_points").on("click", function() {
//                         var points = $("#lp_points_to_use").val();
//                         $.ajax({
//                             type: "POST",
//                             url: "' . admin_url('admin-ajax.php') . '",
//                             data: {
//                                 action: "lp_apply_loyalty_points",
//                                 points: points
//                             },
//                             success: function(response) {
//                                 location.reload();
//                             }
//                         });
//                     });
//                 });
//             </script>';


//         } else {
//             echo '<p>Connectez-vous pour utiliser vos points de fidélité.</p>';
//         }
//     }
// }
// add_action('woocommerce_after_cart', 'lp_display_loyalty_points_field');


//
// add_filter('woocommerce_cart_totals_fee_html', 'lp_add_ajax_remove_button_next_to_fee', 10, 2);
// function lp_add_ajax_remove_button_next_to_fee($fee_html, $fee) {
//     if ($fee->name === 'Réduction Points de Fidélité' && WC()->session->get('lp_loyalty_discount_saved')) {
//         ob_start(); ? >
//         <button type="button" id="lp_ajax_remove_points_btn" class="button" style="margin-left:10px; font-size: 0.8em;">
//             Enlever
//         </button>
//         <script>
//         jQuery(document).ready(function($) {
//             $("#lp_ajax_remove_points_btn").on("click", function(e) {
//                 e.preventDefault();
//                 var $btn = $(this);
//                 $btn.prop('disabled', true).text('Suppression...');

//                 $.post("<?php echo admin_url('admin-ajax.php'); ? >", {
//                     action: "lp_remove_loyalty_points_ajax"
//                 }, function(response) {
//                     if (response.success) {
//                         location.reload(); // Recharge la page pour voir la suppression
//                     } else {
//                         alert(response.data?.erreur || 'Erreur lors de la suppression des points');
//                         $btn.prop('disabled', false).text('Enlever');
//                     }
//                 }).fail(function(xhr) {
//                     alert('Erreur AJAX : ' + xhr.statusText);
//                     $btn.prop('disabled', false).text('Enlever');
//                 });
//             });
//         });
//         </script>
//         <?php
//         $fee_html .= ob_get_clean();
//     }
//     return $fee_html;
// }


// function lp_remove_loyalty_points_ajax() {
//     try {
//         // Nettoyer la session pour désactiver la réduction
//         WC()->session->__unset('lp_loyalty_discount_saved');

//         // Recalcul des totaux (sans réduction cette fois)
//         WC()->cart->calculate_totals();

//         wp_send_json_success();

//     } catch (Exception $e) {
//         error_log('[lp_remove_loyalty_points_ajax] ' . $e->getMessage());
//         wp_send_json_error([
//             'erreur' => $e->getMessage()
//         ]);
//     }
// }

// add_action('wp_ajax_lp_remove_loyalty_points_ajax', 'lp_remove_loyalty_points_ajax');
// add_action('wp_ajax_nopriv_lp_remove_loyalty_points_ajax', 'lp_remove_loyalty_points_ajax');


// add_action('woocommerce_cart_calculate_fees', 'lp_apply_loyalty_discount');
// function lp_apply_loyalty_discount() {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     if (WC()->session->get('lp_loyalty_discount_saved')) {
//         $discount = WC()->session->get('lp_loyalty_discount_saved'); 
				
// 		if ($discount > 0) {
// 			$discount_amount = $discount * 1; // 1 point = 1 MAD TTC

// 			// Récupérer le taux de TVA
// 			$tax_rates = WC_Tax::get_rates('');
// 			$tax_rate = !empty($tax_rates) ? array_shift($tax_rates)['rate'] : 0; 

// 			// Convertir la réduction TTC en HT
// 			if ($tax_rate > 0) {
// 				$discount_ht = $discount_amount / (1 + ($tax_rate / 100));
// 			} else {
// 				$discount_ht = $discount_amount;
// 			}

// 			WC()->cart->add_fee('Réduction Points de Fidélité', -$discount_ht);
// 		}
//     }
// }
//

//
function lp_apply_loyalty_points() {
    if (!isset($_POST['points']) || !is_numeric($_POST['points'])) {
        wp_send_json_error(['message' => 'Valeur de points invalide']);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'Utilisateur non connecté']);
    }

    global $wpdb;
    $points = intval($_POST['points']);

    // Récupérer les points de l'utilisateur
    $user_points = $wpdb->get_var($wpdb->prepare(
        "SELECT points FROM {$wpdb->prefix}loyalty_points WHERE wp_user_id = %d",
        $user_id
    ));

    if ($user_points === null || $points > $user_points) {
        wp_send_json_error(['message' => 'Nombre de points invalide']);
    }

    // Sauvegarde des points appliqués en session WooCommerce
    WC()->session->set('lp_loyalty_discount', $points);

    // Forcer la mise à jour du panier
    WC()->cart->calculate_totals();

    wp_send_json_success(['message' => 'Points appliqués avec succès']);
}
add_action('wp_ajax_lp_apply_loyalty_points', 'lp_apply_loyalty_points');
add_action('wp_ajax_nopriv_lp_apply_loyalty_points', 'lp_apply_loyalty_points');


// function lp_apply_discount() {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $points_used = WC()->session->get('lp_loyalty_discount', 0);

//     if ($points_used > 0) {
//         $discount_amount = $points_used * 1; // 1 point = 1 MAD TTC

//         // Récupérer le taux de TVA
//         $tax_rates = WC_Tax::get_rates('');
//         $tax_rate = !empty($tax_rates) ? array_shift($tax_rates)['rate'] : 0; 

//         // Convertir la réduction TTC en HT
//         if ($tax_rate > 0) {
//             $discount_ht = $discount_amount / (1 + ($tax_rate / 100));
//         } else {
//             $discount_ht = $discount_amount;
//         }

//         // / Ajouter une réduction sans appliquer la taxe
//         WC()->cart->add_fee(__('Réduction Points de Fidélité', 'textdomain'), -$discount_ht, false);

//         // Enregistrer dans la session pour affichage sur la page checkout
//         WC()->session->set('lp_loyalty_discount_saved', $points_used);
//     }
// }
// add_action('woocommerce_cart_calculate_fees', 'lp_apply_discount');

// ➤ 2️⃣ Appliquer la réduction au panier
// function lp_apply_discount() {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $points_used = WC()->session->get('lp_loyalty_discount', 0);

//     if ($points_used > 0) {
//         $discount_amount = $points_used * 1; // 1 point = 1 MAD

//         // Ajouter une réduction sans appliquer la taxe
//         WC()->cart->add_fee(__('Réduction Points de Fidélité', 'textdomain'), -$discount_amount, false);

//         // Enregistrer dans la session pour affichage sur la page checkout
//         WC()->session->set('lp_loyalty_discount_saved', $points_used);
//     }
// }
// add_action('woocommerce_cart_calculate_fees', 'lp_apply_discount');

// ➤ 3️⃣ Sauvegarde des points appliqués dans la commande WooCommerce
/*function lp_save_loyalty_points_to_order_meta($order_id) {
    $points_used = WC()->session->get('lp_loyalty_discount_saved', 0);
    if ($points_used > 0) {
        update_post_meta($order_id, '_lp_loyalty_discount', $points_used);
    }
}
add_action('woocommerce_checkout_update_order_meta', 'lp_save_loyalty_points_to_order_meta');*/

function lp_save_loyalty_points_to_order_meta($order_id) {
    $order = wc_get_order($order_id);
    $points_used = WC()->session->get('loyalty_points_discount_ht', 0);
    
    if ($points_used > 0) {
        // Sauvegarder dans les métadonnées de la commande
        $order->update_meta_data('_points_fidelite_utilises', $points_used);
        $order->save();
        
        // Nettoyer la session
        WC()->session->__unset('loyalty_points_discount_ht');
    }
	
}
add_action('woocommerce_checkout_update_order_meta', 'lp_save_loyalty_points_to_order_meta', 10, 1);


// ➤ 5️⃣ Afficher les points de fidélité appliqués dans l'email de commande
function lp_add_loyalty_points_to_email($order, $sent_to_admin, $plain_text) {
    $points_used = get_post_meta($order->get_id(), '_lp_loyalty_discount', true);
    if ($points_used) {
        echo "<p><strong>Points de Fidélité utilisés :</strong> $points_used</p>";
    }
}
add_action('woocommerce_email_order_meta', 'lp_add_loyalty_points_to_email', 10, 3);

// ➤ 6️⃣ Ajouter un champ dans l'administration pour voir les points appliqués
function lp_display_loyalty_points_in_admin($order) {
    $points_used = get_post_meta($order->get_id(), '_lp_loyalty_discount', true);
    if ($points_used) {
        echo '<p><strong>Points de fidélité utilisés :</strong> ' . esc_html($points_used) . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'lp_display_loyalty_points_in_admin', 10, 1);

// ➤ 7️⃣ Ajouter le script AJAX pour appliquer les points (à inclure dans un shortcode ou widget)
function lp_enqueue_scripts() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            $('#apply_loyalty_points').on('click', function() {
                var points = $('#loyalty_points').val();
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'lp_apply_loyalty_points',
                        points: points
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Points appliqués avec succès !');
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'lp_enqueue_scripts');

//

//
function lp_reset_loyalty_points_session() {
    if (!isset($_POST['points'])) {
        WC()->session->set('lp_loyalty_discount', 0);
    }
}
add_action('woocommerce_before_cart', 'lp_reset_loyalty_points_session');

//

function lp_enqueue_loyalty_script() {
    // Charger le CSS frontend sur le panier
    if (is_cart()) {
        wp_enqueue_style(
            'loyalty-points-frontend-css',
            plugin_dir_url(__FILE__) . 'assets/css/loyalty-points-frontend.css',
            array(),
            '1.3.6'
        );
    }
    
    // Charger le CSS admin sur la page mon compte (pour les tableaux)
    if (is_account_page()) {
        wp_enqueue_style(
            'loyalty-points-admin-css',
            plugin_dir_url(__FILE__) . 'assets/css/loyalty-points-admin.css',
            array(),
            '1.3.6'
        );
    }
    
    if (is_cart()) { // Charger uniquement sur la page panier
        wp_enqueue_script('lp-loyalty-script', plugin_dir_url( __FILE__ ) . 'assets/js/loyalty1.js', array('jquery'), null, true);
        wp_localize_script('lp-loyalty-script', 'ajaxurl', array('ajax_url' => admin_url('admin-ajax.php')));
    
        wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '2.11.0', true);
        wp_enqueue_style('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', array(), '2.11.0');
    }
}
add_action('wp_enqueue_scripts', 'lp_enqueue_loyalty_script');

//
function lp_add_loyalty_points_to_order($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);

    // Vérifier si l'objet commande est valide
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log("Erreur: Impossible de récupérer la commande avec l'ID $order_id");
        return;
    }

	$points_used = $order->get_meta('_points_fidelite_utilises', true);
    //$points_used = WC()->session->get('loyalty_points_discount_ht', 0);
    error_log("Points de fidélité utilisés : " . $points_used);

    if ($points_used > 0) {
        $product_id = lp_get_loyalty_product_id();
        error_log("ID du produit fidélité récupéré : " . $product_id);
        
        if (!$product_id) {
            error_log("Erreur: Impossible de récupérer l'ID du produit fidélité.");
            return;
        }

        // Ajouter les points de fidélité comme un produit à la commande
        $item = new WC_Order_Item_Product();
        $item->set_product_id($product_id);
        $item->set_name(__('Réduction Points de Fidélité', 'textdomain'));
        $item->set_quantity(1);
        $item->set_total(-$points_used); // Valeur négative pour la réduction

        $order->add_item($item);

        // Forcer un total personnalisé pour éviter la double réduction
        /*$new_total = $order->get_subtotal() - $points_used;
        $order->set_total($new_total);*/
		
		// Supprimer toute remise automatique déjà appliquée
        foreach ($order->get_items('coupon') as $item_id => $item) {
            $order->remove_item($item_id);
        }

        // Calculer le total en incluant les frais de livraison
        $subtotal = $order->get_subtotal();
        $shipping_total = $order->get_shipping_total();
        $tax_total = $order->get_total_tax();
        $new_total = ($subtotal + $shipping_total + $tax_total) - $points_used;
        
        // S'assurer que le total ne soit pas négatif
        $new_total = max(0, $new_total);
        $order->set_total($new_total);

        $order->save();
        
        error_log("Produit fidélité ajouté et total ajusté avec succès.");
    }
}
add_action('woocommerce_checkout_order_processed', 'lp_add_loyalty_points_to_order', 20, 1);


function lp_hide_loyalty_product_at_checkout($cart_object) {
    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['data']->get_name() === 'Réduction Points de Fidélité') {
            unset($cart_object->cart_contents[$cart_item_key]); // Supprime du checkout
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'lp_hide_loyalty_product_at_checkout', 10, 1);


function lp_get_loyalty_product_id() {
    $sku = '000000'; // SKU unique du produit fidélité

    // Vérifier si le produit existe déjà via le SKU
    $query = new WC_Product_Query(array(
        'limit'   => 1,
        'sku'     => $sku,
        'return'  => 'ids',
    ));
    $products = $query->get_products();

    if (!empty($products)) {
        return $products[0]; // Retourne l'ID du produit existant
    }

    // Si le produit n'existe pas, on le crée
    $product = new WC_Product_Simple();
    $product->set_name(__('Réduction Points de Fidélité', 'textdomain'));
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden'); // Produit caché du catalogue
    $product->set_sku($sku);
    $product->set_price(0); // Prix initial 0 (sera négatif dans la commande)
    $product->set_regular_price(0);
    $product->set_virtual(true); // Produit virtuel
    $product->set_manage_stock(false); // Désactiver la gestion du stock
    $product->set_stock_status('instock'); // Produit toujours disponible
    $product_id = $product->save();

    return $product_id; // Retourne l'ID du produit créé
}

// /// // // //


/********************************************************************/

function getCustomerIdByEmail($emailCustomer) {
    
    // Récupérer la liste des clients
    $customers = lp_call_api("customers");

    // Vérifier si la réponse est valide
    if (!$customers || !is_array($customers)) {
        return "Erreur lors de la récupération des clients.";
    }

    // Parcourir la liste des clients pour trouver celui avec le bon numéro de téléphone
    foreach ($customers as $customer) {
        if (isset($customer['email']) && trim($customer['email']) === trim($emailCustomer)) {
            return $customer['customers_id']; // Retourne l'ID du client trouvé
        }
    }

    return "Aucun client trouvé avec ce numéro de téléphone.";
}

function getOrdersFromHiboutik($customerId, $saleRef) {    
    $customerSalesUrl = "customer/{$customerId}/sales/";
        
    // Effectuer la requête pour récupérer les commandes du client
    $orders = lp_call_api($customerSalesUrl);
    
    // Vérifier si la réponse est valide
    if (!$orders || !is_array($orders)) {
        return "Erreur lors de la récupération des commandes.";
    }

    // Boucle sur chaque commande pour récupérer les détails
    foreach ($orders as $order) {
        $saleId = $order['sale_id'] ?? null;
        if (!$saleId) {
            continue; // Ignore si pas d'ID de commande
        }

        $saleDetailUrl = "sales/{$saleId}";
        $saleDetails = lp_call_api($saleDetailUrl);               

        // Vérifier si la réponse n'est pas vide et contient bien les données attendues
        if (!empty($saleDetails) && isset($saleDetails[0]['sale_ext_ref'])) {
            $sale = $saleDetails[0];
            $saleExtRef = trim($sale['sale_ext_ref']); // Supprime les espaces inutiles

            // Vérifier si sale_ext_ref correspond au saleRef recherché
            if ($saleExtRef === trim($saleRef)) {
                // echo "Commande trouvée ! sale_id: {$saleId}, sale_ext_ref: {$saleExtRef}\n";
                // return "Oui, une commande avec sale_ext_ref = '{$saleRef}' existe. ID de la commande : {$saleId}";
                return $saleId;
            }
        }        
    }
    
    // Si aucune correspondance n'est trouvée
    return "Aucune commande trouvée avec sale_ext_ref = '{$saleRef}'.";
}

// Remise fidélité
function getProductDetails($saleId) {    
	
	$saleDetailUrl = "sales/{$saleId}";
    $saleDetails = lp_call_api($saleDetailUrl);

    // Vérifier si $saleDetails est bien un tableau et contient des données
    if (!is_array($saleDetails) || empty($saleDetails)) {
        return "Erreur : Données non trouvées";
    }

    // Vérifier que l'index 0 existe
    if (!isset($saleDetails[0])) {
        return "Erreur : Aucune vente trouvée";
    }

    $sale = $saleDetails[0];

    // Vérifier si 'line_items' existe
    if (!isset($sale['line_items']) || !is_array($sale['line_items']) || empty($sale['line_items'])) {
        return "Erreur : Aucun produit trouvé";
    }

    // Parcourir les produits
    foreach ($sale['line_items'] as $product) {
        if (isset($product['product_model']) && trim($product['product_model']) === "Remise fidélité") {
            return $product['line_item_id'];
        }
    }

    return "Non trouvé";
}

// function getProductPoints($saleId) {

//     $saleDetailUrl = "sales/{$saleId}";
//     $saleDetails = lp_call_api($saleDetailUrl);

//     if (!empty($saleDetails)) {
//         $sale = $saleDetails[0];
                
//         // Affichage des détails des produits
//         if (!empty($sale['line_items']) && is_array($sale['line_items'])) {
            
//             foreach ($sale['line_items'] as $product) {
                
//                 if (isset($product['product_model']) && trim($product['product_model']) === "Remise fidélité") {
//                     // echo "🎉 Produit trouvé ! ID de la ligne : {$product['product_price']}\n";
//                     return $product['product_price'];
//                 }
//             }
//         }
//     }
//     return "Non trouvé";
// }

// function getProductPoints($saleId) {

//     $saleDetailUrl = "sales/{$saleId}";
//     $saleDetails = lp_call_api($saleDetailUrl);

//     // Vérifier si $saleDetails est bien un tableau et contient des données
//     if (!is_array($saleDetails) || empty($saleDetails)) {
//         return "Erreur : Données non trouvées";
//     }

//     // Vérifier que l'index 0 existe
//     if (!isset($saleDetails[0])) {
//         return "Erreur : Aucune vente trouvée";
//     }

//     $sale = $saleDetails[0];

//     // Vérifier si 'line_items' existe
//     if (!isset($sale['line_items']) || !is_array($sale['line_items']) || empty($sale['line_items'])) {
//         return "Erreur : Aucun produit trouvé";
//     }

//     // Parcourir les produits
//     foreach ($sale['line_items'] as $product) {
//         if (isset($product['product_model']) && trim($product['product_model']) === "Remise fidélité") {
//             return $product['product_price'];
//         }
//     }

//     return "Non trouvé";
// }

function getProductPoints($saleId) {

    $saleDetailUrl = "sales/{$saleId}";
    $saleDetails = lp_call_api($saleDetailUrl);

    // Vérifier si $saleDetails est bien un tableau et contient des données
    if (!is_array($saleDetails) || empty($saleDetails)) {
        return "Erreur : Données non trouvées";
    }

    // Vérifier que l'index 0 existe
    if (!isset($saleDetails[0])) {
        return "Erreur : Aucune vente trouvée";
    }

    $sale = $saleDetails[0];

    // Vérifier si 'line_items' existe
    if (!isset($sale['line_items']) || !is_array($sale['line_items']) || empty($sale['line_items'])) {
        return "Erreur : Aucun produit trouvé";
    }

    // Parcourir les produits
    foreach ($sale['line_items'] as $product) {
        if (isset($product['product_model']) && trim($product['product_model']) === "Remise fidélité") {
            return $product['product_price'];
        }
    }

    return "Non trouvé";
}
/******************************** */


// function lp_display_order_details_json($order_id) {
//     if (!$order_id) {
//         return;
//     }

//     $order = wc_get_order($order_id);
//     if (!$order) {
//         return;
//     }

//     //

//     // Récupérer les informations du client
//     $user_id = $order->get_user_id();
//     $current_user = get_user_by('id', $user_id);
//     //
//     $Hiboutik_ID = getCustomerIdByEmail($current_user->user_email);
//     // $Hiboutik_ID = 3;
//     $saleRef = "wc_".$order_id; //.$order_id 44716;
//     $Hiboutik_Order_ID = getOrdersFromHiboutik($Hiboutik_ID, $saleRef);
//     $line_item_id = getProductDetails($Hiboutik_Order_ID);
//     $product_points = getProductPoints($Hiboutik_Order_ID);

//     //    
//     $product_points_formatted = rtrim(rtrim(number_format($product_points, 2, '.', ''), '0'), '.');
        
//     // Corps de la requête PUT
//     $body = [
//         'line_item_id' => $line_item_id,
//         'line_item_attribute' => "points",
//         'new_value' => $product_points_formatted
//     ];
    
//     // Appeler l'API pour mettre à jour le produit
//     $update_response = lp_call_api_put("sale_line_item/$line_item_id", $body);
    
//     if (isset($update_response['error'])) {
//         //wp_send_json_error(['message' => 'Erreur Hiboutik : ' . esc_html($update_response['error'])]);        
//          echo '<div class="error"><p>Erreur Hiboutik1 : ' . esc_html($hibou_update_response['error']) . '</p></div>';
//     } else {
//         // wp_send_json_success(['message' => 'Modification appliquée avec succès.']);
//         // die();
//         echo '<div class="error"><p>C\'est bon</p></div>';
//     }

//     //


//     // var_dump($Hiboutik_Order_ID); die();
//     //

//     global $wpdb;
//     $loyalty_table = $wpdb->prefix . 'loyalty_points';
//     $orders_table = $wpdb->prefix . 'loyalty_details';


//     $customer_id = $order->get_user_id();

//     // Récupérer les commandes du client depuis l'API

//     // Récupérer les clients à synchroniser (un seul si $customer_id est fourni)
//     // $query = "SELECT wp_user_id, id FROM $loyalty_table";
//     // if ($customer_id) {       
//     //     $query .= $wpdb->prepare(" WHERE wp_user_id = %d", $customer_id);
//     // }

//     $customer = $wpdb->get_var($wpdb->prepare(
//         "SELECT id FROM $loyalty_table WHERE wp_user_id = %d",
//         $customer_id
//     ));
      
//     $loyalty_id = $customer;

//     $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
//     $raison_detail = "Points échangés pour l'achat de la commande #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
                  
//     //SELECT points_total FROM `wp_loyalty_details` where loyalty_id = 18 ORDER BY id DESC LIMIT 1;
//     $last_points_total = $wpdb->get_var($wpdb->prepare(
//         "SELECT points_total FROM $orders_table WHERE loyalty_id = %d ORDER BY id DESC LIMIT 1",
//         $loyalty_id
//     ));
            
//     // Récupérer les points utilisés
//     $points_used = WC()->session->get('lp_loyalty_discount_saved', 0);
//     $fidelity_discount = $points_used * 1; // 1 point = 1 MAD TTC
    
//     // 
//     $wpdb->insert(
//         $orders_table,
//         [
//             'loyalty_id'       => $loyalty_id,
//             'sale_id_hiboutik' => $Hiboutik_Order_ID,
//             'raison_detail'    => $raison_detail,
//             'order_id'         => $order_id,                                
//             'points_earned'    => 0,
//             'points_redeemed'  => $fidelity_discount,
//             'points_total'     => $last_points_total - $fidelity_discount,
//             'date_log'         => $order_date,                       
//         ],
//         ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
//     );

//     
    
//     //
//     // $hiboutik_id = $customer->hiboutik_id;
//     // if (empty($hiboutik_id)) {
//     //     echo '<div class="error"><p>Erreur : ID Hiboutik manquant.</p></div>';
//     //     return;
//     // }

//     $body = [
//         'customers_id'        => $Hiboutik_ID,
//         'customers_attribute' => "intial_loyalty_points",
//         'new_value'           => $last_points_total
//     ];

//     $hibouk_update_response = lp_call_api_put("customer/$Hiboutik_ID", $body);    
//     $raison_detail = "Points gagnés avec la commande #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
   
//     //

//     $total_points_product = 0;

//     foreach ( $order->get_items() as $item_id => $item ) {
//         $quantity = $item->get_quantity();
        
//         // Calcul des points pour ce produit
//         $price = $item->get_product()->get_price();
//         $points = intval(($price * 0.05) * $quantity); // 5% du total TTC
       
//         // Ajout des points au total
//         $total_points_product += $points;
//     }

//     // Affichage des points totaux gagnés
//     // echo "Points totaux gagnés : " . $total_points_product."<br>";
//     // echo "Last points total :". $last_points_total."<br>";
//     // echo "points total :". $last_points_total + $total_points_product."<br>";

//     if (isset($hibouk_update_response['error'])) {
//         echo '<div class="error"><p>Erreur Hiboutik : ' . esc_html($hibouk_update_response['error']) . '</p></div>';
//     } else {
//         // 
//         $wpdb->insert(
//             $orders_table,
//             [
//                 'loyalty_id'       => $loyalty_id,
//                 'sale_id_hiboutik' => $Hiboutik_Order_ID,
//                 'raison_detail'    => $raison_detail,
//                 'order_id'         => $order_id,
//                 'points_earned'    => $total_points_product,
//                 'points_redeemed'  => 0,
//                 'points_total'     => ($last_points_total - $fidelity_discount) + $total_points_product,
//                 'date_log'         => $order_date,
//             ],
//             ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
//         );
//     }
    
// }

//
add_action('woocommerce_thankyou', 'lp_display_order_details_json', 20);
function lp_display_order_details_json($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Récupérer les informations du client
    $user_id = $order->get_user_id();
    $current_user = get_user_by('id', $user_id);
    //
    $Hiboutik_ID = getCustomerIdByEmail($current_user->user_email);
    $saleRef = "wc_".$order_id; //.$order_id 44716;
    $Hiboutik_Order_ID = getOrdersFromHiboutik($Hiboutik_ID, $saleRef);
    $line_item_id = getProductDetails($Hiboutik_Order_ID);
    $product_points = getProductPoints($Hiboutik_Order_ID);

    //    
    if (isset($product_points) && is_numeric($product_points)) {
		$product_points_formatted = rtrim(rtrim(number_format($product_points, 2, '.', ''), '0'), '.');
        
		// Corps de la requête PUT
		$body = [
			'line_item_id' => $line_item_id,
			'line_item_attribute' => "points",
			'new_value' => $product_points_formatted
		];

		// Appeler l'API pour mettre à jour le produit
		$update_response = lp_call_api_put("sale_line_item/$line_item_id", $body);

		if (isset($update_response['error'])) {
			//wp_send_json_error(['message' => 'Erreur Hiboutik1 : ' . esc_html($update_response['error'])]);        
			 echo '<div class="error"><p>Erreur Hiboutik1 : ' . esc_html($hibou_update_response['error']) . '</p></div>';
		} else {
			// wp_send_json_success(['message' => 'Modification appliquée avec succès.']);
			// die();
			echo '<div class="error"><p>C\'est bon</p></div>';
		}
	}    
	
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'loyalty_points';
    $orders_table = $wpdb->prefix . 'loyalty_details';

    $customer_id = $order->get_user_id();

    // Récupérer ID Client
    $customer = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $loyalty_table WHERE wp_user_id = %d",
        $customer_id
    ));
      
    $loyalty_id = $customer;

    $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
    $raison_detail = "Points échangés pour l'achat de la commande #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
                  
    //SELECT points_total FROM `wp_loyalty_details` where loyalty_id = 18 ORDER BY id DESC LIMIT 1;
    $last_points_total = $wpdb->get_var($wpdb->prepare(
        "SELECT points_total FROM $orders_table WHERE loyalty_id = %d ORDER BY id DESC LIMIT 1",
        $loyalty_id
    ));
            
	var_dump('Points Last :' .$last_points_total);
	 // 2. Récupérer les points utilisés (stockés en métadonnées)
    $points_utilises = $order->get_meta('_points_fidelite_utilises', true);
    $points_utilises_ = floatval($points_utilises);
		
	var_dump('Points utilisés ** :'.$points_utilises);
	
    // Récupérer les points utilisés
	/*$points_used = WC()->session->get('loyalty_points_discount_ht', 0);
    var_dump('Points utilisés ***:'.$points_used);
	*/
    $fidelity_discount = $points_utilises_ * 1; // 1 point = 1 MAD TTC
    
    // 
    $wpdb->insert(
        $orders_table,
        [
            'loyalty_id'       => $loyalty_id,
            'sale_id_hiboutik' => $Hiboutik_Order_ID,
            'raison_detail'    => $raison_detail,
            'order_id'         => $order_id,                                
            'points_earned'    => 0,
            'points_redeemed'  => $points_utilises_,
            'points_total'     => $last_points_total - $points_utilises_,
            'date_log'         => $order_date,                       
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
    );
    
	//
    $body = [
        'customers_id'        => $Hiboutik_ID,
        'customers_attribute' => "intial_loyalty_points",
        'new_value'           => $last_points_total - $fidelity_discount
    ];
    $hibou_update_response = lp_call_api_put("customer/$Hiboutik_ID", $body);   
    	     
    // $raison_detail = "Points gagnés avec la commande #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
   

    //
    // // $total_points_product = 0;
    // // foreach ( $order->get_items() as $item_id => $item ) {
    // //     $quantity = $item->get_quantity();
		
    // //     // Calcul des points pour ce produit
    // //     $price = $item->get_product()->get_price();
    // //     $points = intval(($price * 0.05) * $quantity); // 5% du total TTC        		
		
    // //     // Ajout des points au total
    // //     $total_points_product += $points;
    // // }

   
	
	// // 2. Récupérer la réduction appliquée
    // $reduction_ht = 0;
    // $reduction_ttc = 0;
    // $tva = 0;

    // foreach ($order->get_fees() as $fee) {
    //     if (strpos(strtolower($fee->get_name()), 'points fidélité') !== false) {
    //         $reduction_ht = abs($fee->get_amount()); // Montant HT
    //         $reduction_ttc = abs($fee->get_total()); // Montant TTC
    //         break;
    //     }
    // }
	
	// $formated_reduction_ttc = number_format($reduction_ttc, 2, '.', '');	
	
	// $tva = number_format($reduction_ht * 0.2, 2, '.', '');	
	// $points_u = $tva + $formated_reduction_ttc;	

    // // Calcul du total des produits (hors livraison)
    // $order_items_total = 0;
    // // foreach ( $order->get_items() as $item ) {
    // //     $order_items_total += $item->get_total(); // Total TTC pour l'article
    // //     //$order_items_total += $item->get_subtotal(); // Total HT pour l'article
    // // }
    // foreach ( $order->get_items() as $item ) {
    //     $order_items_total += $item->get_product()->get_price() * $item->get_quantity(); // Prix unitaire HT * quantité
    // }

	// $total_points_product_cart = $order_items_total - $points_u;
	
    // // Déterminer le pourcentage de points à appliquer
    // if ( $total_points_product_cart < 300 ) {
    //     $rate = 0.05; // 5%
    // } elseif ( $total_points_product_cart < 500 ) {
    //     $rate = 0.08; // 8%
    // } else {
    //     $rate = 0.10; // 10%
    // }

    // var_dump($points_u);  
        
    // // Calcul des points totaux
    // $total_points_product = intval($total_points_product_cart * $rate);  
    // var_dump($total_points_product_cart); 
	
		
    // $total_points_user = ($last_points_total - $fidelity_discount) + $total_points_product;
    // // $total_points_user = $last_points_total - $fidelity_discount;

    // if (isset($hibouk_update_response['error'])) {
    //     echo '<div class="error"><p>Erreur Hiboutik 2 : ' . esc_html($hibouk_update_response['error']) . '</p></div>';
    // } else {
    //     // 
    //     $wpdb->insert(
    //         $orders_table,
    //         [
    //             'loyalty_id'       => $loyalty_id,
    //             'sale_id_hiboutik' => $Hiboutik_Order_ID,
    //             'raison_detail'    => $raison_detail,
    //             'order_id'         => $order_id,
    //             'points_earned'    => $total_points_product,
    //             'points_redeemed'  => 0,
    //             'points_total'     => $total_points_user,
    //             'date_log'         => $order_date,
    //         ],
    //         ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
    //     );
    // }    

    // Modification du total des points de l'utilisateur
    $wpdb->update(
        $loyalty_table,
        ['points' => $last_points_total - $fidelity_discount],
        ['id' => $loyalty_id],
        ['%d'],
        ['%d']
    );
	
}

//
add_action('woocommerce_order_status_completed', 'lp_on_order_completed', 20, 1);


function lp_on_order_completed($order_id) {
    $order = wc_get_order($order_id);

    // Récupérer les informations du client
    $user_id = $order->get_user_id();
    $current_user = get_user_by('id', $user_id);
    //
    $Hiboutik_ID = getCustomerIdByEmail($current_user->user_email);

    $saleRef = "wc_".$order_id; //.$order_id 44716;
    $Hiboutik_Order_ID = getOrdersFromHiboutik($Hiboutik_ID, $saleRef);
    $line_item_id = getProductDetails($Hiboutik_Order_ID);
    $product_points = getProductPoints($Hiboutik_Order_ID);
    //

    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'loyalty_points';
    $orders_table = $wpdb->prefix . 'loyalty_details';


    $customer_id = $order->get_user_id();

    // Récupérer ID Client
    $customer = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $loyalty_table WHERE wp_user_id = %d",
        $customer_id
    ));
      
    $loyalty_id = $customer;

    $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
 
    $last_points_total = $wpdb->get_var($wpdb->prepare(
        "SELECT points_total FROM $orders_table WHERE loyalty_id = %d ORDER BY id DESC LIMIT 1",
        $loyalty_id
    ));
        

    // Vérifier que la commande vient bien de l'état "processing"
    if ($order && $order->get_status() === 'completed') {               
       
        $total_points_product = 0;

        foreach ( $order->get_items() as $item_id => $item ) {
            $quantity = $item->get_quantity();
            
            // Calcul des points pour ce produit
            $price = $item->get_product()->get_price();
            $pourcentage = get_option('lp_percentage_points')/ 100;
            $points = intval(($price * $pourcentage) * $quantity); // 5% du total TTC        		
            
            // Ajout des points au total
            $total_points_product += $points;
        }
            
        // Affichage des points totaux gagnés
        $total_points_user = $last_points_total + $total_points_product;

		 // Modification du total des points de l'utilisateur sur Hiboutik
        $body = [
            'customers_id'        => $Hiboutik_ID,
            'customers_attribute' => "intial_loyalty_points",
            'new_value'           => $total_points_user
        ];
        
        $raison_detail = "Points gagnés avec la commande #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
        
        // Récupérer les points utilisés (stockés en métadonnées)
        $points_utilises = $order->get_meta('_points_fidelite_utilises', true);
        $points_utilises = floatval($points_utilises);
        
        // Récupérer la réduction appliquée
        $reduction_ht = 0;
        $reduction_ttc = 0;
        $tva = 0;
        
        foreach ($order->get_fees() as $fee) {
            if (strpos(strtolower($fee->get_name()), 'points fidélité') !== false) {
                $reduction_ht = abs($fee->get_amount()); // Montant HT
                $reduction_ttc = abs($fee->get_total()); // Montant TTC
                break;
            }
        }
            
        $formated_reduction_ttc = number_format($reduction_ttc, 2, '.', '');	
        
        $tva = number_format($reduction_ht * 0.2, 2, '.', '');	
        $points_u = $tva + $formated_reduction_ttc;	
    
        // Calcul du total des produits (hors livraison)
        $order_items_total = 0;            
        foreach ( $order->get_items() as $item ) {
            $order_items_total += $item->get_product()->get_price() * $item->get_quantity(); // Prix unitaire HT * quantité
        }
        
        $total_points_product_cart = $order_items_total - $points_u;
        
        // Déterminer le pourcentage de points à appliquer
        if ( $total_points_product_cart < 300 ) {
            $rate = 0.05; // 5%
        } elseif ( $total_points_product_cart < 500 ) {
            $rate = 0.08; // 8%
        } else {
            $rate = 0.10; // 10%
        }
        
        var_dump($points_u);  
            
        // Calcul des points totaux
        $total_points_product = intval($total_points_product_cart * $rate);  
        var_dump($total_points_product_cart); 
        
            
        $total_points_user = ($last_points_total - $fidelity_discount) + $total_points_product;
        // $total_points_user = $last_points_total - $fidelity_discount;
                
        $hibouk_update_response = lp_call_api_put("customer/$Hiboutik_ID", $body);

        if (isset($hibouk_update_response['error'])) {
            echo '<div class="error"><p>Erreur Hiboutik 2 : ' . esc_html($hibouk_update_response['error']) . '</p></div>';
        } else {
            // 
            $wpdb->insert(
                $orders_table,
                [
                    'loyalty_id'       => $loyalty_id,
                    'sale_id_hiboutik' => $Hiboutik_Order_ID,
                    'raison_detail'    => $raison_detail,
                    'order_id'         => $order_id,
                    'points_earned'    => $total_points_product,
                    'points_redeemed'  => 0,
                    'points_total'     => $total_points_user,
                    'date_log'         => $order_date,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
            );
        }
    
        // Modification du total des points de l'utilisateur
        $wpdb->update(
            $loyalty_table,
            ['points' => $total_points_user],
            ['id' => $loyalty_id],
            ['%d'],
            ['%d']
        );        
    }
}
//

add_action('woocommerce_order_status_cancelled', 'action_on_order_cancelled', 10, 1);

function action_on_order_cancelled($order_id) {
    $order = wc_get_order($order_id);
    
    // Récupérer les informations du client    	
    $user_id = $order->get_user_id();
	$current_user = get_user_by('id', $user_id);
	
    //
    $Hiboutik_ID = getCustomerIdByEmail($current_user->user_email);
    $saleRef = "wc_".$order_id;
    $Hiboutik_Order_ID = getOrdersFromHiboutik($Hiboutik_ID, $saleRef);
    // $line_item_id = getProductDetails($Hiboutik_Order_ID);
    // $product_points = getProductPoints($Hiboutik_Order_ID);
    
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'loyalty_points';
    $orders_table = $wpdb->prefix . 'loyalty_details';

      
    // Récupérer ID Client
    $customer_id = $order->get_user_id();
    $customer = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $loyalty_table WHERE wp_user_id = %d",
        $customer_id
    ));
      
    $loyalty_id = $customer;	
	
    $raison_detail = "Points retirés sur la commande annulée #<a href='" . admin_url("post.php?post={$order_id}&action=edit") . "' target='_blank'>{$order_id}</a>";
   
    $last_points_total = $wpdb->get_var($wpdb->prepare(
        "SELECT points_total FROM $orders_table WHERE loyalty_id = %d ORDER BY id DESC LIMIT 1",
        $loyalty_id
    ));
	//var_dump($last_points_total);
	
    // Récupérer les points utilisés
    $points_used = get_post_meta($order->get_id(), '_lp_loyalty_discount', true);
		
    $total_points_user = $last_points_total + $points_used;
	$order_date = $order->get_date_created()->date('Y-m-d H:i:s');

	//
    $total_points_product = 0;

    foreach ( $order->get_items() as $item_id => $item ) {
        $quantity = $item->get_quantity();
		
        // Calcul des points pour ce produit
        $price = $item->get_product()->get_price();        
        $pourcentage = get_option('lp_percentage_points')/ 100;
        $points = intval(($price * $pourcentage) * $quantity); // 5% du total TTC
        				
        // Ajout des points au total
        $total_points_product += $points;
    }
    //$total_points_product

    $wpdb->insert(
        $orders_table,
        [
            'loyalty_id'       => $loyalty_id,
            'sale_id_hiboutik' => $Hiboutik_Order_ID,
            'raison_detail'    => $raison_detail,
            'order_id'         => $order_id,                                
            'points_earned'    => 0,
            'points_redeemed'  => $total_points_product,
            'points_total'     => $last_points_total - $total_points_product,
            'date_log'         => $order_date,                       
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
    );

    //s
    $wpdb->insert(
        $orders_table,
        [
            'loyalty_id'       => $loyalty_id,
            'sale_id_hiboutik' => $Hiboutik_Order_ID,
            'raison_detail'    => $raison_detail,
            'order_id'         => $order_id,                                
            'points_earned'    => $points_used,
            'points_redeemed'  => 0,
            'points_total'     => ($last_points_total - $total_points_product) + $points_used,
            'date_log'         => $order_date,                       
        ],
        ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
    );
	
}


/*add_filter('woocommerce_currency_symbol', 'change_existing_currency_symbol', 10, 2);

function change_existing_currency_symbol( $currency_symbol, $currency ) {
    switch( $currency ) {
        case 'MAD': $currency_symbol = 'MAD';
        break;
    }
    return $currency_symbol;
}*/


//
function lp_associate_new_user($user_id) {
    global $wpdb;

    // Validation de base
    if (!is_numeric($user_id) || $user_id <= 0) {
        error_log("ID utilisateur invalide: " . var_export($user_id, true));
        return false;
    }

    // Récupérer le numéro de téléphone avec gestion d'erreurs
    $billing_phone = get_user_meta($user_id, 'phone_number', true);
    
    if (empty($billing_phone)) {
        error_log("Aucun numéro de téléphone trouvé pour l'utilisateur ID: " . $user_id);
        return false;
    }

    // Validation du numéro de téléphone
    $billing_phone = preg_replace('/\D/', '', $billing_phone);
    if (!preg_match('/^\d+$/', $billing_phone)) {
        error_log("Format de numéro invalide pour l'utilisateur ID: " . $user_id);
        return false;
    }

    // Requête SQL avec protection contre les erreurs
    $loyalty_user = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM wp_loyalty_points WHERE phone = %s",
        $billing_phone
    ));

    if (!$loyalty_user || !is_object($loyalty_user)) {
        error_log("Aucune correspondance trouvée pour le numéro: " . $billing_phone);
        return false;
    }

    // Mise à jour avec gestion d'erreurs
    $updated = $wpdb->update(
        'wp_loyalty_points',
        ['wp_user_id' => $user_id],
        ['id' => $loyalty_user->id],
        ['%d'],
        ['%d']
    );

    // Journalisation détaillée
    if ($updated === false) {
        $error = $wpdb->last_error;
        error_log("Échec de la mise à jour pour l'utilisateur WP ID: " . 
                 $user_id . ". Erreur SQL: " . var_export($error, true));
        return false;
    }

    error_log("Mise à jour réussie pour l'utilisateur WP ID: " . $user_id);
    return true;
}

add_action('user_register', 'lp_associate_new_user', 10, 1);
add_action('woocommerce_created_customer', 'lp_associate_new_user');
add_action('profile_update', 'lp_associate_new_user');
//


// Ajouter le numéro de téléphone dans le tableau de bord "Mon compte"
function add_log_to_my_account_dashboard() {
    $user_id = get_current_user_id();
	
	echo "id user: ".$user_id;

    echo '<h2 class="lp_title">Mes Points de Fidélité</h2>';    
    
    global $wpdb;
    $table_name_orders = $wpdb->prefix . 'loyalty_details';
    $table_name_customers = $wpdb->prefix . 'loyalty_points';

    // Récupérer la somme des points total     
    $total_points = $wpdb->get_var($wpdb->prepare("SELECT points FROM $table_name_customers WHERE wp_user_id = %d", $user_id));
   
     
     // Sécurité : valeur par défaut si NULL
     $total_points = $total_points ? floatval($total_points) : 0;
	
	echo $total_points;
 
     // Formater avec 2 décimales et affichage en dirhams
     $formatted_points = number_format($total_points, 2, ',', ' ');
 
     // Obtenir la devise du site WooCommerce
     $currency_code = get_option('woocommerce_currency');
     $currency_symbol = get_woocommerce_currency_symbol($currency_code);
 
    
	 // Récupérer la somme des points total     
    $hiboutik_id = $wpdb->get_var($wpdb->prepare("SELECT hiboutik_id FROM $table_name_customers WHERE wp_user_id = %d", $user_id));
		
	
	$customer_id = "customer/{$hiboutik_id}/";
    $customer = lp_call_api($customer_id);

	echo $customer[0]['loyalty_points'];
			
	 // Affichage     
     echo '<h4 class="lp-total-points">Total des points : ' . $customer[0]['loyalty_points'] . ' (' . $customer[0]['loyalty_points'] . ' ' . esc_html($currency_symbol) . ')</h4>';

       
    // Récupérer le nombre total de commandes
    $total_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(lo.id) FROM wp_loyalty_details AS lo JOIN wp_loyalty_points AS lp ON lo.loyalty_id = lp.id WHERE lp.wp_user_id = %d", $user_id));
   

    echo '<div class="wrap">';

    // PAGINATION
    $per_page = 4;
    $paged = isset($_GET['lp_page']) ? max(1, intval($_GET['lp_page'])) : 1;
    $offset = ($paged - 1) * $per_page;

    // Total d'entrées
    $total_orders = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(lo.id)
         FROM {$table_name_orders} AS lo
         JOIN {$table_name_customers} AS lp ON lo.loyalty_id = lp.id
         WHERE lp.wp_user_id = %d",
        $user_id
    ));

    $total_pages = ceil($total_orders / $per_page);

    // Résultats paginés
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT lo.*, lp.name
         FROM {$table_name_orders} AS lo
         JOIN {$table_name_customers} AS lp ON lo.loyalty_id = lp.id
         WHERE lp.wp_user_id = %d
         ORDER BY lo.date_log DESC
         LIMIT %d OFFSET %d",
        $user_id, $per_page, $offset
    ));

    if (!empty($orders)) {
        echo '<table class="widefat fixed" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="text-align: left;">No</th>
                        <th style="text-align: left;">Username</th>
                        <th style="text-align: left;">Raison en Details</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>';

        $index = 1;
        foreach ($orders as $order) {
            $details_id = 'details-' . $order->id;
            echo "<tr>
                    <td>{$index}</td>
                    <td>{$order->name}</td>
                    <td>{$order->raison_detail}</td>
                    <td style='text-align: center;'><button class='toggle-details' data-target='{$details_id}'>+</button></td>
                  </tr>
                  <tr id='{$details_id}' class='details-row' style='display:none; background: #f9f9f9;'>
                    <td colspan='4'>
                        <strong>Points Gagnés:</strong> {$order->points_earned}<br>
                        <strong>Points Utilisés:</strong> {$order->points_redeemed}<br>
                        <strong>Total Points:</strong> " . number_format($order->points_total, 2, ',', ' ') . " <br>
                        <strong>Date:</strong> " . date('d-m-Y H:i:s', strtotime($order->date_log)) . "
                    </td>
                  </tr>";
            $index++;
        }

        echo '</tbody></table>';

        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav" style="margin-top: 15px;">';
            if ($paged > 1) {
                echo '<a class="prev page-numbers" href="?paged=' . ($paged - 1) . '">&laquo;</a> ';
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $paged) {
                    echo '<span class="page-numbers current">' . $i . '</span> ';
                } else {
                    echo '<a class="page-numbers" href="?paged=' . $i . '">' . $i . '</a> ';
                }
            }
            if ($paged < $total_pages) {
                echo '<a class="next page-numbers" href="?paged=' . ($paged + 1) . '">&raquo;</a>';
            }
            echo '</div>';
        }
    } else {
        echo '<p>Aucune commande trouvée pour ce client.</p>';
    }

    echo '</div>';

    // Script JS
    echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".toggle-details").forEach(function(button) {
            button.addEventListener("click", function () {
                const targetId = this.dataset.target;
                const targetRow = document.getElementById(targetId);
                if (targetRow.style.display === "none") {
                    targetRow.style.display = "";
                    this.textContent = "−";
                } else {
                    targetRow.style.display = "none";
                    this.textContent = "+";
                }
            });
        });
    });
    </script>'; 
}
add_action('woocommerce_account_dashboard', 'add_log_to_my_account_dashboard');

//



/*********** */


// Affichage du bouton "Offert" dans le panier et affichage des boutons dans le panier
add_filter('woocommerce_after_cart_item_name', 'afficher_bouton_offert_par_points', 10, 2);
function afficher_bouton_offert_par_points($cart_item, $cart_item_key) {
    if (!is_user_logged_in()) return;

    // 1. Récupérer les points du client et les points déjà utilisés dans le panier
    $points_totaux = get_user_loyalty_points();
    $points_utilises = calculate_used_points_in_cart();
    $points_disponibles = $points_totaux - $points_utilises;
    
    if ($points_disponibles < 1) return; // Au moins 1 point disponible requis

    $product = $cart_item['data'];
    $quantite = $cart_item['quantity'];
    $prix_unitaire = $product->get_price(); // Prix en DH

    // 2. Calculer combien d'articles peuvent être offerts (1 point = 1 DH)
    // Prendre en compte les points disponibles après déduction des points déjà utilisés
    $max_offert_possible = floor($points_disponibles / $prix_unitaire);
    
    // Si le produit est déjà offert, on l'affiche quand même pour permettre l'annulation
    $est_offert = isset($cart_item['is_gifted_by_points']) && $cart_item['is_gifted_by_points'];
    
    if (!$est_offert && $max_offert_possible < 1) {
        return; // Ne pas afficher le bouton si pas assez de points pour ce produit
    }

    $qty_offerte = isset($cart_item['points_discount_qty']) ? $cart_item['points_discount_qty'] : 0;

    echo '<div class="offert-buttons" data-key="'.esc_attr($cart_item_key).'">';
    
    if (!$est_offert) {
        if ($quantite > 1) {
            // Afficher le sélecteur de quantité masqué par défaut
            echo '<div class="offert-qty-selector" style="display:none; margin-bottom:10px;">';
            echo '<input type="number" class="offert-qty-input" min="1" max="'.min($quantite, $max_offert_possible).'" value="1" style="width:60px; padding:5px; margin-right:5px;">';
            echo '<button class="button confirm-offert" data-key="'.esc_attr($cart_item_key).'" data-prix="'.esc_attr($prix_unitaire).'">OK</button>';
            echo '</div>';
            
            // Bouton principal pour les produits avec quantité > 1
            echo '<button class="button show-qty-selector" data-key="'.esc_attr($cart_item_key).'" data-prix="'.esc_attr($prix_unitaire).'">';
            echo '🎁 Offert pour 1 ou plus';
            echo '</button>';
        } else {
            // Bouton simple pour quantité = 1
            echo '<button class="button appliquer-offert-simple" data-key="'.esc_attr($cart_item_key).'" data-prix="'.esc_attr($prix_unitaire).'" data-qty="1">';
            echo '🎁 Offert 100% ('.wc_price($prix_unitaire).')';
            echo '</button>';
        }
    } else {
        echo '<button class="button retirer-offert" data-key="'.esc_attr($cart_item_key).'">';
        echo '❌ Annuler l\'offre';
        echo '</button>';
    }
    echo '</div>';
}

// Fonction helper pour récupérer les points utilisateur
function get_user_loyalty_points() {
    global $wpdb;
    $user_id = get_current_user_id();
    $points = $wpdb->get_var($wpdb->prepare(
        "SELECT points FROM {$wpdb->prefix}loyalty_points WHERE wp_user_id = %d",
        $user_id
    ));
    return $points !== null ? intval($points) : 0;
}

// Fonction helper pour calculer les points utilisés dans le panier
function calculate_used_points_in_cart() {  
    $points_utilises = 0;
    foreach (WC()->cart->get_cart() as $item) {
        if (isset($item['is_gifted_by_points']) && $item['is_gifted_by_points']) {
            $qty = $item['points_discount_qty'] ?? $item['quantity'];
            $points_utilises += $item['data']->get_price() * $qty;
        }
    }
    return $points_utilises;
}
//
add_action('wp_ajax_get_cart_totals_fragment', 'get_cart_totals_fragment');
add_action('wp_ajax_nopriv_get_cart_totals_fragment', 'get_cart_totals_fragment');
function get_cart_totals_fragment() {
    ob_start();
    woocommerce_cart_totals();
    $totals = ob_get_clean();
    
    // Inclure également les points dans la réponse
    ob_start();
    afficher_points_utilises();
    $points = ob_get_clean();
    
    $output = '<div class="cart_totals">' . $totals . $points . '</div>';
    
    echo $output;
    wp_die();
}


// Script JS pour la gestion dynamique
add_action('wp_footer', 'bouton_offert_script_js');
function bouton_offert_script_js() {
    if (!is_cart()) return;
    ?>
    <script>
    jQuery(document).ready(function($){

        // Variable globale pour stocker les données de points
        var pointsData = {
            totaux: <?php echo get_user_loyalty_points(); ?>,
            utilises: <?php echo calculate_used_points_in_cart(); ?>,
            restants: <?php echo get_user_loyalty_points() - calculate_used_points_in_cart(); ?>
        };

        // Fonction pour mettre à jour les données de points
        function updatePointsData() {
            $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
                action: 'get_points_data',
                security: "<?php echo wp_create_nonce('offert-par-points'); ?>"
            }, function(response) {
                if (response.success) {
                    pointsData = {
                        totaux: response.data.points_totaux,
                        utilises: response.data.points_utilises,
                        restants: response.data.points_restants
                    };
                    updatePointsDisplay();
                }
            }, 'json');
        }

        // Fonction pour mettre à jour l'affichage des points
        function updatePointsDisplay() {
            $('.total-points').text(pointsData.totaux.toLocaleString('fr-FR') + ' points');
            $('.used-points').text(pointsData.utilises.toLocaleString('fr-FR') + ' points');
            $('.remaining-points').text(pointsData.restants.toLocaleString('fr-FR') + ' points');
        }

        // Appeler la fonction au chargement pour masquer les boutons si nécessaire
        updateAllOfferButtons();

        // Afficher/masquer le sélecteur de quantité
        $(document).on("click", ".show-qty-selector", function(e){
            e.preventDefault();
            var container = $(this).closest('.offert-buttons');
            container.find('.offert-qty-selector').show();
            $(this).hide();
        });

        // Confirmer la quantité sélectionnée
        $(document).on("click", ".confirm-offert", function(e){
            e.preventDefault();
            var button = $(this);
            var container = button.closest('.offert-buttons');
            var qty = parseInt(container.find('.offert-qty-input').val());
            var prix_unitaire = parseFloat(button.data('prix'));
            var total = prix_unitaire * qty;
            
            // Vérifier que la quantité est valide
            var maxQty = parseInt(container.find('.offert-qty-input').attr('max'));
            if (qty < 1 || qty > maxQty) {
                alert('Veuillez entrer une quantité valide entre 1 et ' + maxQty);
                return;
            }

            // Vérifier si les points sont suffisants
            if (total > pointsData.restants) {
                var message = 'Désolé, vous n\'avez pas assez de points.\n';
                message += 'Points disponibles: ' + pointsData.restants + '\n';
                message += 'Points nécessaires: ' + total;
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Points insuffisants',
                        text: message,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(message);
                }
                return;
            }

            // Envoyer la requête AJAX si tout est OK
            $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
                action: 'appliquer_produit_offert',
                cart_item_key: button.data('key'),
                qty: qty,
                security: "<?php echo wp_create_nonce('offert-par-points'); ?>"
            }, function(response){
                if (response.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Succès',
                            text: 'L\'offre a été appliquée avec succès',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    }
                    
                    // Mettre à jour l'affichage
                    container.html('<button class="button retirer-offert" data-key="'+button.data('key')+'" data-prix="'+prix_unitaire+'" data-qty="'+qty+'">❌ Enlever offre ('+(prix_unitaire*qty)+' DH)</button>');
                    
                    // Mettre à jour les données de points
                    updatePointsData();
                    updateAllOfferButtons();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Erreur',
                            text: response.data.message || 'Une erreur est survenue',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                }
            }, 'json').fail(function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Problème de connexion au serveur',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });

        // Gestion du clic sur le bouton "Offert 100%"
        $(document).on("click", ".appliquer-offert-simple", function(e){
            e.preventDefault();
            var button = $(this);
            var container = button.closest('.offert-buttons');
            var prix_unitaire = parseFloat(button.data('prix'));
            var qty = 1;
            
            if (prix_unitaire > pointsData.restants) {
                alert('Vous n\'avez pas assez de points pour cette offre');
                return;
            }

            $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
                action: 'appliquer_produit_offert',
                cart_item_key: button.data('key'),
                qty: qty,
                security: "<?php echo wp_create_nonce('offert-par-points'); ?>"
            }, function(response){
                if (response.success) {
                    container.html('<button class="button retirer-offert" data-key="'+button.data('key')+'" data-prix="'+prix_unitaire+'" data-qty="1">❌ Enlever offre ('+prix_unitaire+' DH)</button>');
                    
                    updatePointsData();
                    updateAllOfferButtons();
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Succès',
                            text: 'Produit offert à 100%',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then (() => {
							location.reload();
						})
                    }
                } else {
                    alert(response.data.message || 'Erreur lors de l\'application de l\'offre');
                }
            }, 'json');
        });

        // Fonction pour mettre à jour tous les boutons "Offert" dans le panier
        function updateAllOfferButtons() {
            // Parcourir tous les boutons "Offert" non activés
            $('.offert-buttons').each(function(){
                var container = $(this);
                var cartItemKey = container.data('key');
                var prixUnitaire = parseFloat(
                    container.find('.appliquer-offert, .appliquer-offert-simple, .show-qty-selector').data('prix') || 
                    container.find('.retirer-offert').data('prix') || 0
                );
                
                // Si c'est un bouton "Annuler", on ne fait rien
                if (container.find('.retirer-offert').length > 0) {
                    return true; // continue
                }
                
                // Calculer le maximum offert possible pour ce produit
                var maxOffertPossible = Math.floor(pointsData.restants / prixUnitaire);
                var quantite = 1;
                
                // Récupérer la quantité du produit dans le panier
                $('[name="cart['+cartItemKey+'][qty]"]').each(function(){
                    quantite = parseInt($(this).val()) || 1;
                });
                
                // Si pas assez de points pour ce produit, masquer le bouton
                if (maxOffertPossible < 1) {
                    container.hide();
                } else {
                    container.show();
                    
                    // Mettre à jour le max du sélecteur de quantité si visible
                    var qtySelector = container.find('.offert-qty-selector');
                    if (qtySelector.length) {
                        var newMax = Math.min(quantite, maxOffertPossible);
                        qtySelector.find('input').attr('max', newMax);
                        
                        // Ajuster la valeur si elle dépasse le nouveau max
                        var currentVal = parseInt(qtySelector.find('input').val()) || 1;
                        if (currentVal > newMax) {
                            qtySelector.find('input').val(newMax);
                        }
                    }
                }
            });
        }

        // Gestion des boutons retirer offre
        $(document).on("click", ".retirer-offert", function(e){
            e.preventDefault();
            var button = $(this);
            var container = button.closest('.offert-buttons');
            var prix_unitaire = parseFloat(button.data('prix'));
            var cart_item_key = button.data('key');
            
            // Récupérer la quantité actuelle du produit dans le panier
            var quantite = 1;
            $('[name="cart['+cart_item_key+'][qty]"]').each(function(){
                quantite = parseInt($(this).val()) || 1;
            });

            $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
                action: 'retirer_produit_offert',
                cart_item_key: cart_item_key,
                security: "<?php echo wp_create_nonce('offert-par-points'); ?>"
            }, function(response){
                if (response.success) {
                    // Recréer l'interface selon la quantité
                    if (quantite > 1) {
                        container.html(
                            '<div class="offert-qty-selector" style="display:none; margin-bottom:10px;">' +
                            '<input type="number" class="offert-qty-input" min="1" max="'+quantite+'" value="1" style="width:60px; padding:5px; margin-right:5px;">' +
                            '<button class="button confirm-offert" data-key="'+cart_item_key+'" data-prix="'+prix_unitaire+'">OK</button>' +
                            '</div>' +
                            '<button class="button show-qty-selector" data-key="'+cart_item_key+'" data-prix="'+prix_unitaire+'">' +
                            '🎁 Offert pour 1 ou plus' +
                            '</button>'
                        );
						location.reload();
						
                    } else {
                        container.html(
                            '<button class="button appliquer-offert-simple" data-key="'+cart_item_key+'" data-prix="'+prix_unitaire+'" data-qty="1">' +
                            '🎁 Offert 100% ('+prix_unitaire+' DH)' +
                            '</button>'
                        );
						location.reload();
                    }
                    
                    updatePointsData();
                    updateAllOfferButtons();
                }
            }, 'json');
        });

        // Mettre à jour les points quand le panier change
        $(document.body).on('updated_wc_div', function() {
            updatePointsData();
        });
    });
    </script>
    <?php
}

// Gestion AJAX
add_action('wp_ajax_appliquer_produit_offert', 'appliquer_produit_offert');
add_action('wp_ajax_retirer_produit_offert', 'retirer_produit_offert');

function appliquer_produit_offert() {
    check_ajax_referer('offert-par-points', 'security');
    
    $key = sanitize_text_field($_POST['cart_item_key']);
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
    $cart = WC()->cart;
    
    if ($cart_item = $cart->get_cart_item($key)) {
        $prix_unitaire = $cart_item['data']->get_price();
        $total_item = $prix_unitaire * $qty;
        $points_disponibles = get_user_loyalty_points() - calculate_used_points_in_cart();
        
        if ($points_disponibles >= $total_item) {
            $cart->cart_contents[$key]['is_gifted_by_points'] = true;
            $cart->cart_contents[$key]['points_discount_qty'] = $qty;
            $cart->set_session();
            
            wp_send_json_success(array(
                'message' => 'Offre appliquée avec succès'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Points insuffisants. Points disponibles: ' . $points_disponibles
            ));
        }
    }
    
    wp_send_json_error(array(
        'message' => 'Erreur lors du traitement'
    ));
}


function retirer_produit_offert() {
    check_ajax_referer('offert-par-points', 'security');
    
    $key = sanitize_text_field($_POST['cart_item_key']);
    $cart = WC()->cart;
    
    if (isset($cart->cart_contents[$key]['is_gifted_by_points'])) {
        unset($cart->cart_contents[$key]['is_gifted_by_points']);
        unset($cart->cart_contents[$key]['points_discount_qty']);
        $cart->set_session();
        wp_send_json_success(array(
            'message' => 'Offre retirée avec succès'
        ));
    }
    wp_send_json_error(array(
        'message' => 'Erreur lors du retrait de l\'offre'
    ));
}

// Application de la réduction
add_action('woocommerce_cart_calculate_fees', 'appliquer_remise_offert_produit', 20, 1);
function appliquer_remise_offert_produit($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!is_user_logged_in()) return;

    $reduction_totale = 0;
    $points_utilises = false;
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['is_gifted_by_points']) && $cart_item['is_gifted_by_points']) {
            $qty = $cart_item['points_discount_qty'] ?? $cart_item['quantity'];
            $prix_unitaire = $cart_item['data']->get_price();
            $reduction_item = $prix_unitaire * $qty;
            $reduction_totale += $reduction_item;
            $points_utilises = true;
            
            // Récupérer le nom du produit
            $product_name = $cart_item['data']->get_name();
			$product_sku = $cart_item['data']->get_sku();
            
            // Ajouter une fee individuelle pour chaque produit offert
            $cart->add_fee(
                'Réduction Points de Fidélité - ' .  "Produit: " . $product_name . " [SKU: " . $product_sku . "]" . ' (x' . $qty . ')',
                -$reduction_item,
                false // Non taxable
            );
        }
    }
    	
    if ($reduction_totale > 0 && $points_utilises) {
        // Sauvegarder dans la session
        WC()->session->set('loyalty_points_discount_ht', $reduction_totale);
    } else {
        // S'assurer que la session est nettoyée si aucun point n'est utilisé
        WC()->session->__unset('loyalty_points_discount_ht');
    }
}


/*function appliquer_remise_offert_produit($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!is_user_logged_in()) return;

    $reduction_totale = 0;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['is_gifted_by_points']) && $cart_item['is_gifted_by_points']) {
            $qty = $cart_item['points_discount_qty'] ?? $cart_item['quantity'];
            $prix_unitaire = $cart_item['data']->get_price();
            
            // Prix HT (sans TVA)
            $prix_ht = wc_get_price_including_tax($cart_item['data']);
            $reduction_totale += $prix_ht * $qty;
        }
    }
    
    if ($reduction_totale > 0) {
        // Enregistrer le montant HT dans la session
        WC()->session->set('loyalty_points_discount_ht', $reduction_totale);
                
        // Ajouter la réduction (HT)
        $cart->add_fee(
            'Réduction Points de Fidélité',
            -$reduction_totale,
            false // Non taxable
        );
    }
}*/



// Affichage des points dans le panier
add_action('woocommerce_cart_totals_before_order_total', 'afficher_points_utilises');
function afficher_points_utilises() {
    if (!is_user_logged_in()) return;
    
    $points = get_user_loyalty_points();
    $points_utilises = calculate_used_points_in_cart();
    $points_restants = $points - $points_utilises;
    
    // Ajoutez une classe wrapper pour faciliter la mise à jour
    echo '<div class="points-summary">';
    echo '<tr class="points-info">
        <th>Vos points</th>
        <td class="total-points">'.number_format($points, 0, ',', ' ').' points</td>
    </tr>
    <tr class="points-info">
        <th>Points utilisés</th>
        <td class="used-points">'.number_format($points_utilises, 0, ',', ' ').' points</td>
    </tr>
    <tr class="points-info">
        <th>Points restants</th>
        <td class="remaining-points">'.number_format($points_restants, 0, ',', ' ').' points</td>
    </tr>';
    echo '</div>';
}


// 1. Autoriser les requêtes à cet endpoint
add_action('init', function() {
    add_rewrite_rule('^trigger-lp-sync/?$', 'index.php?trigger_lp_sync=1', 'top');
});

// 2. Ajouter la variable de requête
add_filter('query_vars', function($vars) {
    $vars[] = 'trigger_lp_sync';
    return $vars;
});

// 3. Exécuter la synchro quand l'endpoint est appelé
add_action('template_redirect', function() {
    if (get_query_var('trigger_lp_sync')) {
        // 🔐 Vérifier la clé secrète (remplacez "VOTRE_CLE_SECRETE" par un mot de passe fort)
        $secret_key = 'MaCleSuperSecrete123!';
        if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
            wp_die('Accès refusé', 403); // Bloque les appels non autorisés
        }

        // 🔄 Lancer la synchro
        lp_sync_customers();

        // ✅ Réponse JSON (pour vérification)
        wp_send_json_success(['message' => 'Synchronisation OK à ' . date('H:i:s')]);
    }
});

// 4. Rafraîchir les permaliens (à faire UNE FOIS après l'ajout du code)
flush_rewrite_rules(false); // Supprimez cette ligne après la 1ère exécution


/**/

add_action('template_redirect', function() {
    if (get_query_var('trigger_lp_sync')) {
        $secret_key = 'MaCleSuperSecrete123!';
        if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
            wp_die('Accès refusé', 403);
        }

        // Type de synchro (customers ou orders)
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'customers';
        
        if ($type === 'orders') {
            lp_sync_orders();
            $message = 'Commandes synchronisées';
        } else {
            lp_sync_customers();
            $message = 'Clients synchronisés';
        }

        wp_send_json_success([
            'message' => $message,
            'type' => $type,
            'time' => date('H:i:s')
        ]);
    }
});


add_action('rest_api_initt', function() {
    register_rest_route('lp-sync/v1', '/trigger', [
        'methods'  => 'GET',
        'callback' => function() {
            // Clé secrète (identique à l'URL)
            $secret_key = 'MaCleSuperSecrete123!';
            
            if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
                return new WP_Error('forbidden', 'Accès refusé', ['status' => 403]);
            }

            lp_sync_customers();
            return ['success' => true, 'message' => 'Sync OK'];
        },
    ]);
});


/***/
define('MYSTORE2024_KEY', 'MaKeySecreteMystore123!'); // Choisis une bonne clé

// Exécuter la synchro et notifier Make
function lp_run_sync_and_notify_make() {
    // 1. Appeler la fonction de synchronisation
    lp_sync_customers();

    // 2. Appeler le webhook Make
    $webhook_url = 'https://hook.eu2.make.com/nfgv1335bdklygkodr9wcwl7evvp0r6i'; // Mets ici ton vrai lien webhook Make

    wp_remote_post($webhook_url, array(
        'method'    => 'POST',
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => json_encode(array(
            'message'   => 'Synchronisation des clients terminée.',
            'timestamp' => current_time('mysql'),
        )),
    ));
}

add_action('rest_api_init', function () {
    register_rest_route('lp/v1', '/sync-customers/', array(
        'methods' => 'GET',
        'callback' => function () {
            lp_run_sync_and_notify_make();
            return rest_ensure_response(['success' => true, 'message' => 'Synchronisation OK']);
        },
        'permission_callback' => function () {
            $key_param = $_GET['key'] ?? '';
            return $key_param === MYSTORE2024_KEY;
        }
    ));
});


//https://website.ma/wp-json/lp/v1/sync-customers/?key=MaKeySecreteMystore123!