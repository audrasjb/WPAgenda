<?php 
/*
Plugin Name: AJB Agenda
Plugin URI: http://…/
Description: test
Author: JBA
Version: 0.1
Author URI: http://jeanbaptisteaudras.com/
*/


/**************
 Création du type de contenu personnalisé	
*/
function ajb_agenda_BO_create_cpt() {
	$labels = array(
	    'name' => 'Agenda',
    	'singular_name' => 'Agenda',
    	'add_new' => 'Ajouter',
    	'add_new_item' => 'Ajouter un événement',
    	'edit_item' => 'Modifier',
    	'new_item' => 'Nouveau',
    	'all_items' => 'Tous les événements',
    	'view_item' => 'Visualiser',
    	'search_items' => 'Rechercher',
    	'not_found' =>  'Aucun contenu trouvé',
    	'not_found_in_trash' => 'Aucun contenu trouvé dans la corbeille', 
    	'parent_item_colon' => '',
    	'menu_name' => 'Agenda'
  	);
 	$args = array(
    	'labels' => $labels,
    	'public' => true,
    	'publicly_queryable' => true,
    	'show_ui' => true, 
    	'show_in_menu' => true, 
    	'query_var' => true,
    	'rewrite' => array( 'slug' => 'agenda' ),
    	'capability_type' => 'post',
    	'has_archive' => true, 
    	'hierarchical' => false,
    	'menu_icon' => plugins_url() . '/agenda/inc/images/ico-agenda.png',
    	'supports' => array( 'title', 'editor', 'thumbnail', 'revision' )
  	); 
	register_post_type( 'agenda', $args );
	$rubLabels = array(
		'name' => 'Thématiques',
		'singular_name' => 'Thématiques',
		'search_items' => 'Rechercher',
		'popular_items' => 'Thématiques souvent utilisées',
		'all_items' => 'Toutes les thématiques',
		'edit_item' => 'Modifier une thématique',
		'update_item' => 'Mettre à jour une thématique',
		'add_new_item' => 'Ajouter une thématique',
		'new_item_name' => 'Nouvelle thématique'
	);
	// Rubriques.
	register_taxonomy('agenda-thematique', 'agenda', array(
		'hierarchical' => true,
		'label' => 'Thématiques',
		'labels' => $rubLabels,
		'rewrite' => array( 'slug' => 'agenda-thematique' ),
		'show_in_nav_menus' => false,
		'show_admin_column' => true
	));

	// Déploiement des colonnes de la liste des contenus
	function ajb_agenda_BO_deploy_colums( $columns ) {
		$columns = array(
		'cb' => '<input type="checkbox" />',
		'title' => __( 'Titre' ),
		'agenda-thematique' => __( 'Thématiques' ),
		'agenda-datedebut' => __( 'Date de début' ),
		'agenda-datefin' => __( 'Date de fin' ),
		'date' => __( 'Dernière modification' )
		);
		return $columns;
	}
	add_filter( 'manage_edit-agenda_columns', 'ajb_agenda_BO_deploy_colums' ) ;

	// Future v2 : Pour le tri, voir ==> http://wordpress.org/support/topic/admin-column-sorting

	// Remplissage des lignes du tableau
	add_action( 'manage_agenda_posts_custom_column', 'ajb_agenda_BO_fillin_colums', 10, 2 );
	function ajb_agenda_BO_fillin_colums( $column, $post_id ) {
		global $post;
		switch( $column ) {
			case 'agenda-datedebut' :
				$datedebut = get_post_meta( $post_id, 'agenda-datedebut', true );
				if (empty($datedebut))
					echo '<span style="color: #f50;">Non renseigné</span>';
				else
					echo ''.$datedebut;
				break;
			case 'agenda-datefin' :
				$datefin = get_post_meta( $post_id, 'agenda-datefin', true );
				if (empty($datefin))
					echo '<span style="color: #f50;">Non renseigné</span>';
				else
					$datejour = date('d/m/Y');
					$explodedDateJour = explode("/", $datejour);
					$explodedDateFin = explode("/", $datefin);
					$ISdateJour = $explodedDateJour[2].$explodedDateJour[1].$explodedDateJour[0];
					$ISdateFin = $explodedDateFin[2].$explodedDateFin[1].$explodedDateFin[0];
					if ($ISdateFin != '') {
						if ($ISdateJour>$ISdateFin) { 
							// L'événement est fini
							echo '<strike>'. $datefin . '</strike> <span style="color: #f50;">(terminé)</span>';
						} else {
							// L'événement est encore d'actualité
							echo ''.$datefin;
						}
					}
				break;
			case 'agenda-thematique' :
				$terms = get_the_terms( $post_id, 'agenda-thematique' );
				/* Si des taxonomies sont trouvées */
				if (!empty($terms)) {
					$out = array();
					/* On boucle sur chacune des taxonomies et on place un lien */
					foreach ( $terms as $term ) {
						$out[] = sprintf( '<a href="%s">%s</a>',
							esc_url( add_query_arg( array( 'post_type' => $post->post_type, 'agenda-thematique' => $term->slug ), 'edit.php' ) ),
							esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, 'agenda-thematique', 'display' ) )
						);
					}
					/* On associe les taxonomies, séparées par une virgule */
					echo join( ', ', $out );
				}
				/* Si aucune taxonomie n'est trouvée, on affiche un message */
				else {
					_e( 'Aucune thématique' );
				}
				break;
			default :
				break;
		}
	}
}

/**************
 Création des metaboxes
*/
function ajb_agenda_BO_create_metabox() {
	add_meta_box('ajb-agenda-metabox', 'Début / Fin', 'ajb_agenda_BO_construct_metabox', 'agenda', 'side', 'high');
}

/**************
 Construction de la metabox
*/
function ajb_agenda_BO_construct_metabox() {
	// Afficher les dates déjà enregistrées (le cas échéant).
	global $post;
	$datedebut = get_post_meta($post->ID, 'agenda-datedebut', true);
	$datefin = get_post_meta($post->ID, 'agenda-datefin', true);
 
	// Utilisation de Nonce pour la vérification des champs (sécurité)
	wp_nonce_field( plugin_basename(__FILE__), 'ajb_agenda_nonce');
	// Opérations sur les dates pour obtenir un format de comparaison
	$datejour = date('d/m/Y');
	$explodedDateJour = explode("/", $datejour);
	$explodedDateFin = explode("/", $datefin);
	$ISdateJour = $explodedDateJour[2].$explodedDateJour[1].$explodedDateJour[0];
	$ISdateFin = $explodedDateFin[2].$explodedDateFin[1].$explodedDateFin[0];
	if ($ISdateFin == '') {} else {
		if ($ISdateJour>$ISdateFin) {
	    	// C'est fini
	    	echo '<p style="color: #c50;">Ce contenu ne s\'affiche plus sur le site car sa date de fin est inférieure à celle d\'aujourd\'hui.</p>';
	    } else {
	    	// C'est encore d'actualité
		    echo '<p style="color: #33cc33">Ce contenu est actuellement affiché sur le site.</p>';
	    }
	}
	?>
	<p>Date de début : <br /><input id="date-debut" name="date-debut" type="text" value="<?php echo $datedebut; ?>" /></p>
	<p>Date de fin : <br /><input id="date-fin" name="date-fin" type="text" value="<?php echo $datefin; ?>" /></p>
	<p style="font-style: italic; color: #777;">La date de fin est utilisée pour l'arrêt de l'affichage du contenu sur le site. <br />Pour un événement se déroulant sur un seul jour, renseigner la même date dans les deux champs.</p>
	<?php
}

/**************
 Enregistrer les valeurs saisies.
*/
function ajb_agenda_BO_save_post($post_id) {
 	// Vérifier si la méta existe. Sinon, et bien on va l'ajouter !
 	// on utilise d'abord add_post_meta, qui s'exécute uniquement si la méta n'existe pas encore pour ce contenu, dans la BDD
	$datedebut = $_POST['date-debut'];
	add_post_meta($post_id, 'agenda-datedebut', $datedebut, true);
	update_post_meta($post_id, 'agenda-datedebut', $datedebut);
	$datefin = $_POST['date-fin'];
	add_post_meta($post_id, 'agenda-datefin', $datefin, true);
	update_post_meta($post_id, 'agenda-datefin', $datefin);
	// ajout des variables destinées au tri des contenus côté front-office
	$explodedDatedebutTri = explode("/", $datedebut);
	$datedebutTri = $explodedDatedebutTri[2].$explodedDatedebutTri[1].$explodedDatedebutTri[0];
	add_post_meta($post_id, 'agenda-tridatedebut', $datedebutTri, true);
	update_post_meta($post_id, 'agenda-tridatedebut', $datedebutTri);
}
add_action('save_post', 'ajb_agenda_BO_save_post');

// Ajout du script jQuery UI datePicker pour les nouveaux posts (http://jqueryui.com/demos/datepicker)
function ajb_agenda_BO_jquery_datepicker_JS() {
	wp_enqueue_script(
		'jquery-ui-datepicker',
		plugins_url() . '/agenda/inc/js/jquery-ui.min.js',
		array('jquery')
	);
	wp_enqueue_script(
		'jba-datepicker',
		plugins_url() . '/agenda/inc/js/ajb-datepicker.js',
		array('jquery', 'jquery-ui-datepicker')
	);
}
add_action('admin_print_scripts-post-new.php', 'ajb_agenda_BO_jquery_datepicker_JS');
add_action('admin_print_scripts-post.php', 'ajb_agenda_BO_jquery_datepicker_JS');

// Ajout de la CSS de datePicker
function ajb_agenda_BO_jquery_datepicker_CSS() {
	wp_enqueue_style(
		'jquery-ui-datepicker',
		plugins_url() . '/agenda/inc/css/ajb-datepicker.css'
	);
}
add_action('admin_print_styles-post-new.php', 'ajb_agenda_BO_jquery_datepicker_CSS');
add_action('admin_print_styles-post.php', 'ajb_agenda_BO_jquery_datepicker_CSS');	


/**************
 Création de la fonction permettant d'afficher l'agenda entier
*/
function get_ajb_agenda() {
	echo '<h2>Liste des événements</h2>';
	$args =	array(
				'post_type' 		=>	'agenda',
				'post_status'		=> 'publish',
				'posts_per_page' 	=>	'-1',
				'meta_key'			=>	'agenda-tridatedebut',				
				'order'				=>	'ASC',
				'orderby'			=>	'meta_value_num'
			);
	$the_query = new WP_Query( $args );
	if ( $the_query->have_posts() ) {
		while ( $the_query->have_posts() ) {
			$the_query->the_post();
			// Récupération des dates/heures de début/fin
			$datedebut = get_post_meta(get_the_ID(), 'agenda-datedebut', true);
			$datefin = get_post_meta(get_the_ID(), 'agenda-datefin', true);
			// utilisation de explode() pour obtenir un array comparable avec $datejour
			$explodedDateFin = explode("/", $datefin);
			// comparaison de la date avec la date du jour
			$ISdateJour = date('Ymd');
			$ISdateFin = $explodedDateFin[2].$explodedDateFin[1].$explodedDateFin[0];
			if ( $ISdateFin!='' ) {
				if ( $ISdateJour <= $ISdateFin ) { 
					// L'événement est encore d'actualité : on l'affiche
					$content = '';
					$content .= '<article>';
					$content .= '<h3><a href="' .get_permalink(). '">' . get_the_title(). '</a></h3>';
					$content .= '<p><span>Publié dans la thématique ';
					$content .= get_the_term_list( get_the_ID(), 'agenda-thematique', '', ', ', '' );
					$content .= '</span></p>';
					// Si $datedebut = $datefin, alors l'événement se déroule sur un seul jour 
					if ( $datedebut === $datefin ) {
						$content .= '<p><strong>Le '.$datefin.'</strong></p>';
					} else {
						$content .= '<p><strong>Du '.$datedebut.' au '.$datefin.'</strong></p>';
					}
					$content .= '<p>' . get_the_excerpt() . ' <span role="presentation">(…) </span>';
					$content .= '<a title="Lien permanent «' . get_the_title() . '»" href="' . get_permalink() . '">&gt; Lire la suite</a></p>';
					$content .= '</article>';
					$content .= '<hr />'; // séparateur
					echo $content;
				}
			}
		}
	} else {
		'<p>Il n\'y a aucun événement à venir</p>';
	}
	wp_reset_postdata();
}

/**************
 Initialisation du module
*/
add_action('init', 'ajb_agenda_BO_create_cpt');
add_action('add_meta_boxes', 'ajb_agenda_BO_create_metabox');

?>