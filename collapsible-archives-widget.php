<?php
/*
  Plugin Name: Collapsible Archives Widget
  Description: Adds a widget which displays collapsible archive links organized by year then month. CSS-only, no JavaScript.
*/

/* Copyright (C) 2017 Stephen T. Robbins
 * License: GPLv3 : https://choosealicense.com/licenses/agpl-3.0/
*/

/* -- Known Issues, Suggested Updates/Improvements, and Notices --
  NONE
*/

/* Modified example from http://www.wpbeginner.com/wp-tutorials/how-to-create-a-custom-wordpress-widget/ */





// Register and load the widget
function caw_load_widget() {
  register_widget( 'caw_widget' );
}
add_action( 'widgets_init', 'caw_load_widget' );

// Creating the widget 
class caw_widget extends WP_Widget {

  function __construct() {
    parent::__construct(
      // Base ID of your widget
      'caw_widget', 

      // Widget name will appear in UI
      __('Collapsible Archives', 'caw_widget_domain'), 

      // Widget description
      array( 'description' => __( 'Monthly archive links collapsiblely organized by year then month.', 'caw_widget_domain' ), ) 
    );
  }

  // Creating widget front-end

  public function widget( $args, $instance ) {
    wp_enqueue_style( 'caw_widget-style', plugins_url('css-accordion.min.css', __FILE__) );

    $title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Archives' ) : $instance['title'] );

    // before and after widget arguments are defined by themes
    echo $args['before_widget'];
    if ( ! empty( $title ) ) {
      echo $args['before_title'] . $title . $args['after_title'];
    }

    echo '<ul class="css-accordion">';





    /* Modified wp_get_archives() from wp/wp-includes/general-template.php  */
    global $wpdb, $wp_locale;

    $r = array(
      'format' => 'html',
      'show_post_count' => true,
      'order' => 'DESC',
      'post_type' => 'post'
    );

    // $r = wp_parse_args( $args, $defaults );

    $post_type_object = get_post_type_object( $r['post_type'] );
    if ( ! is_post_type_viewable( $post_type_object ) ) {
      return;
    }
    $r['post_type'] = $post_type_object->name;

    $order = strtoupper( $r['order'] );
    if ( $order !== 'ASC' ) {
      $order = 'DESC';
    }

    $sql_where = $wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $r['post_type'] );
    $where = apply_filters( 'getarchives_where', $sql_where, $r );
    $join = apply_filters( 'getarchives_join', '', $r );
    $last_changed = wp_cache_get_last_changed( 'posts' );

    $output = '';
    $query = "SELECT YEAR(post_date) AS `year`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date) ORDER BY post_date $order";
    $key = md5( $query );
    $key = "wp_get_archives:$key:$last_changed";
    if ( ! $results_years = wp_cache_get( $key, 'posts' ) ) {
      $results_years = $wpdb->get_results( $query );
      wp_cache_set( $key, $results_years, 'posts' );
    }
    $query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date $order";
    $key = md5( $query );
    $key = "wp_get_archives:$key:$last_changed";
    if ( ! $results_months = wp_cache_get( $key, 'posts' ) ) {
      $results_months = $wpdb->get_results( $query );
      wp_cache_set( $key, $results_months, 'posts' );
    }

    if ( $results_years && $results_months ) {
      // $after = '';
      foreach ( (array) $results_years as $result_year ) {
        if ( $r['show_post_count'] ) {
          $after = '&nbsp;(' . $result_year->posts . ')';
        }
        $output .= '<li>
  <input id="archive-' . $result_year->year . '" type="checkbox" name="archive_years">
  <label for="archive-' . $result_year->year . '"><span class="archive_year">' . $result_year->year . '</span>' . $after . '</label>
  <div class="content">
    <ul class="css-accordion">
        ';

        if ( $results_months ) {
          // $after = '';
          foreach ( (array) $results_months as $result_month ) {
            if ($result_month->year == $result_year->year) {
              $url = get_month_link( $result_month->year, $result_month->month );
              if ( 'post' !== $r['post_type'] ) {
                $url = add_query_arg( 'post_type', $r['post_type'], $url );
              }
              /* translators: 1: month name */
              $text = sprintf( __( '%1$s' ), $wp_locale->get_month( $result_month->month ) );
              if ( $r['show_post_count'] ) {
                $after = '&nbsp;(' . $result_month->posts . ')';
              }
              $output .= get_archives_link( $url, $text, 'html', '', $after );
            }
          }
        }

        $output .= '    </ul>
  </div>
</li>';
      }
    }
    echo $output;





    echo '</ul>';
    echo $args['after_widget'];
  }

  // Widget Backend 
  public function form( $instance ) {
    if ( isset( $instance[ 'title' ] ) ) {
      $title = $instance[ 'title' ];
    }
    else {
      $title = __( 'Archives', 'caw_widget_domain' );
    }
// Widget admin form
?>
<p>
<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
</p>
<?php 
  }

  // Updating widget replacing old instances with new
  public function update( $new_instance, $old_instance ) {
    $instance = array();
    $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
    return $instance;
  }
} // Class caw_widget ends here

?>
