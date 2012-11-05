<?php
/**
 * Name: Migrate: Pods 1.x Packages
 *
 * Menu Name: Migrate Packages
 *
 * Description: Import/Export your Pods, Fields, and other settings from any Pods 1.x site or export to any Pods 2.0 site; Includes an API to import/export Packages.
 *
 * Version: 2.0
 *
 * Developer Mode: on
 *
 * @package Pods\Components
 * @subpackage Migrate-Packages
 */

class Pods_Migrate_Packages extends PodsComponent {

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {

    }

    /**
     * Enqueue styles
     *
     * @since 2.0.0
     */
    public function admin_assets () {
        wp_enqueue_style( 'pods-wizard' );
    }

    /**
     * Build admin area
     *
     * @param $options
     *
     * @since 2.0.0
     */
    public function admin ( $options, $component ) {
        $method = 'import'; // ajax_import

        pods_view( PODS_DIR . 'components/Migrate-Packages/ui/wizard.php', compact( array_keys( get_defined_vars() ) ) );
    }

    /**
     * Handle the Import/Export AJAX
     *
     * @param $params
     */
    public function ajax_import ( $params ) {
        $data = $params->import_package;

        $imported = $this->import( $data );

        if ( !empty( $imported ) )
            echo '<p>Import Complete! The following items were imported:</p>';

        foreach ( $imported as $type => $import ) {
            echo '<h4>' . ucwords( $type ) . '</h4>';

            echo '<ul class="normal">';

            foreach ( $import as $k => $what ) {
                echo '<li>' . esc_html( $what ) . '</li>';
            }

            echo '</ul>';
        }
    }

    /**
     * Import a Package
     *
     * @param string|array $data a JSON array package string, or an array of Package Data
     * @param bool $replace Whether to replace existing pods entirely or just update them
     *
     * @return array|bool
     *
     * @static
     * @since 2.0.5
     */
    public static function import ( $data, $replace = false ) {
        if ( !defined( 'PODS_FIELD_STRICT' ) )
            define( 'PODS_FIELD_STRICT', false );

        if ( !is_array( $data ) ) {
            $json_data = @json_decode( $data, true );

            if ( !is_array( $json_data ) )
                $json_data = @json_decode( stripslashes( $data ), true );

            $data = $json_data;
        }

        if ( !is_array( $data ) || empty( $data ) )
            return false;

        $api = pods_api();

        if ( !isset( $data[ 'meta' ] ) || !isset( $data[ 'meta' ][ 'version' ] ) || empty( $data[ 'meta' ][ 'version' ] ) )
            return false;

        // Pods 1.x < 1.10
        if ( false === strpos( $data[ 'meta' ][ 'version' ], '.' ) && (int) $data[ 'meta' ][ 'version' ] < 1000 )
            $data[ 'meta' ][ 'version' ] = implode( '.', str_split( $data[ 'meta' ][ 'version' ] ) );
        // Pods 1.10 <= 2.0
        elseif ( false === strpos( $data[ 'meta' ][ 'version' ], '.' ) )
            $data[ 'meta' ][ 'version' ] = pods_version_to_point( $data[ 'meta' ][ 'version' ] );

        $found = array();

        if ( isset( $data[ 'pods' ] ) && is_array( $data[ 'pods' ] ) ) {
            foreach ( $data[ 'pods' ] as $pod_data ) {
                if ( isset( $pod_data[ 'id' ] ) )
                    unset( $pod_data[ 'id' ] );

                $pod = $api->load_pod( array( 'name' => $pod_data[ 'name' ] ), false );

                if ( !empty( $pod ) ) {
                    // Delete Pod if it exists
                    if ( $replace ) {
                        $api->delete_pod( array( 'id' => $pod[ 'id' ] ) );

                        $pod = array( 'fields' => array() );
                    }
                }
                else
                    $pod = array( 'fields' => array() );

                // Backwards compatibility
                if ( version_compare( $data[ 'meta' ][ 'version' ], '2.0.0', '<' ) ) {
                    $core_fields = array(
                        array(
                            'name' => 'created',
                            'label' => 'Date Created',
                            'type' => 'datetime',
                            'options' => array(
                                'datetime_format' => 'ymd_slash',
                                'datetime_time_type' => '12',
                                'datetime_time_format' => 'h_mm_ss_A'
                            ),
                            'weight' => 1
                        ),
                        array(
                            'name' => 'modified',
                            'label' => 'Date Modified',
                            'type' => 'datetime',
                            'options' => array(
                                'datetime_format' => 'ymd_slash',
                                'datetime_time_type' => '12',
                                'datetime_time_format' => 'h_mm_ss_A'
                            ),
                            'weight' => 2
                        ),
                        array(
                            'name' => 'author',
                            'label' => 'Author',
                            'type' => 'pick',
                            'pick_object' => 'user',
                            'options' => array(
                                'pick_format_type' => 'single',
                                'pick_format_single' => 'autocomplete',
                                'default_value' => '{@user.ID}'
                            ),
                            'weight' => 3
                        )
                    );

                    if ( !empty( $pod_data[ 'fields' ] ) ) {
                        foreach ( $pod_data[ 'fields' ] as $k => $field ) {
                            $field_type = $field[ 'coltype' ];

                            if ( 'txt' == $field_type )
                                $field_type = 'text';
                            elseif ( 'desc' == $field_type )
                                $field_type = 'wysiwyg';
                            elseif ( 'code' == $field_type )
                                $field_type = 'paragraph';
                            elseif ( 'bool' == $field_type )
                                $field_type = 'boolean';
                            elseif ( 'num' == $field_type )
                                $field_type = 'number';
                            elseif ( 'date' == $field_type )
                                $field_type = 'datetime';

                            $multiple = min( max( (int) $field[ 'multiple' ], 0 ), 1 );

                            $new_field = array(
                                'name' => trim( $field[ 'name' ] ),
                                'label' => trim( $field[ 'label' ] ),
                                'description' => trim( $field[ 'comment' ] ),
                                'type' => $field_type,
                                'weight' => (int) $field[ 'weight' ],
                                'options' => array(
                                    'required' => min( max( (int) $field[ 'required' ], 0 ), 1 ),
                                    'unique' => min( max( (int) $field[ 'unique' ], 0 ), 1 ),
                                    'input_helper' => $field[ 'input_helper' ]
                                )
                            );

                            if ( in_array( $new_field[ 'name' ], array( 'created', 'modified', 'author' ) ) )
                                $new_field[ 'name' ] .= '2';

                            if ( 'pick' == $field_type ) {
                                $new_field[ 'pick_object' ] = 'pod-' . $field[ 'pickval' ];

                                if ( 'wp_user' == $field[ 'pickval' ] )
                                    $new_field[ 'pick_object' ] = 'user';
                                elseif ( 'wp_post' == $field[ 'pickval' ] )
                                    $new_field[ 'pick_object' ] = 'post_type-post';
                                elseif ( 'wp_page' == $field[ 'pickval' ] )
                                    $new_field[ 'pick_object' ] = 'post_type-page';
                                elseif ( 'wp_taxonomy' == $field[ 'pickval' ] )
                                    $new_field[ 'pick_object' ] = 'taxonomy-category';

                                // This won't work if the field doesn't exist
                                // $new_field[ 'sister_id' ] = $field[ 'sister_field_id' ];

                                $new_field[ 'options' ][ 'pick_filter' ] = $field[ 'pick_filter' ];
                                $new_field[ 'options' ][ 'pick_orderby' ] = $field[ 'pick_orderby' ];
                                $new_field[ 'options' ][ 'pick_display' ] = '{@name}';
                                $new_field[ 'options' ][ 'pick_size' ] = 'medium';

                                if ( 1 == $multiple ) {
                                    $new_field[ 'options' ][ 'pick_format_type' ] = 'multi';
                                    $new_field[ 'options' ][ 'pick_format_multi' ] = 'checkbox';
                                    $new_field[ 'options' ][ 'pick_limit' ] = 0;
                                }
                                else {
                                    $new_field[ 'options' ][ 'pick_format_type' ] = 'single';
                                    $new_field[ 'options' ][ 'pick_format_single' ] = 'dropdown';
                                    $new_field[ 'options' ][ 'pick_limit' ] = 1;
                                }
                            }
                            elseif ( 'file' == $field_type ) {
                                $new_field[ 'options' ][ 'file_format_type' ] = 'multi';
                                $new_field[ 'options' ][ 'file_type' ] = 'any';
                            }
                            elseif ( 'number' == $field_type )
                                $new_field[ 'options' ][ 'number_decimals' ] = 2;
                            elseif ( 'desc' == $field[ 'coltype' ] )
                                $new_field[ 'options' ][ 'wysiwyg_editor' ] = 'tinymce';
                            elseif ( 'text' == $field_type )
                                $new_field[ 'options' ][ 'text_max_length' ] = 128;

                            if ( isset( $pod[ 'fields' ][ $new_field[ 'name' ] ] ) )
                                $new_field = array_merge_recursive( $pod[ 'fields' ][ $new_field[ 'name' ] ], $new_field );

                            $pod_data[ 'fields' ][ $k ] = $new_field;
                        }
                    }

                    if ( pods_var( 'id', $pod, 0 ) < 1 )
                        $pod_data[ 'fields' ] = array_merge( $core_fields, $pod_data[ 'fields' ] );

                    if ( empty( $pod_data[ 'label' ] ) )
                        $pod_data[ 'label' ] = ucwords( str_replace( '_', ' ', $pod_data[ 'name' ] ) );

                    if ( isset( $pod_data[ 'is_toplevel' ] ) ) {
                        $pod_data[ 'show_in_menu' ] = ( 1 == $pod_data[ 'is_toplevel' ] ? 1 : 0 );

                        unset( $pod_data[ 'is_toplevel' ] );
                    }

                    if ( isset( $pod_data[ 'detail_page' ] ) ) {
                        $pod_data[ 'detail_url' ] = $pod_data[ 'detail_page' ];

                        unset( $pod_data[ 'detail_page' ] );
                    }

                    if ( isset( $pod_data[ 'before_helpers' ] ) ) {
                        $pod_data[ 'pre_save_helpers' ] = $pod_data[ 'before_helpers' ];

                        unset( $pod_data[ 'before_helpers' ] );
                    }

                    if ( isset( $pod_data[ 'after_helpers' ] ) ) {
                        $pod_data[ 'post_save_helpers' ] = $pod_data[ 'after_helpers' ];

                        unset( $pod_data[ 'after_helpers' ] );
                    }

                    if ( isset( $pod_data[ 'pre_drop_helpers' ] ) ) {
                        $pod_data[ 'pre_delete_helpers' ] = $pod_data[ 'pre_drop_helpers' ];

                        unset( $pod_data[ 'pre_drop_helpers' ] );
                    }

                    if ( isset( $pod_data[ 'post_drop_helpers' ] ) ) {
                        $pod_data[ 'post_delete_helpers' ] = $pod_data[ 'post_drop_helpers' ];

                        unset( $pod_data[ 'post_drop_helpers' ] );
                    }

                    $pod_data[ 'name' ] = pods_clean_name( $pod_data[ 'name' ] );

                    $pod_data = array(
                        'name' => $pod_data[ 'name' ],
                        'label' => $pod_data[ 'label' ],
                        'type' => 'pod',
                        'storage' => 'table',
                        'fields' => $pod_data[ 'fields' ],
                        'options' => array(
                            'pre_save_helpers' => pods_var_raw( 'pre_save_helpers', $pod_data ),
                            'post_save_helpers' => pods_var_raw( 'post_save_helpers', $pod_data ),
                            'pre_delete_helpers' => pods_var_raw( 'pre_delete_helpers', $pod_data ),
                            'post_delete_helpers' => pods_var_raw( 'post_delete_helpers', $pod_data ),
                            'show_in_menu' => ( 1 == pods_var_raw( 'show_in_menu', $pod_data, 0 ) ? 1 : 0 ),
                            'detail_url' => pods_var_raw( 'detail_url', $pod_data ),
                            'pod_index' => 'name'
                        ),
                    );
                }

                $pod = array_merge( $pod, $pod_data );

                foreach ( $pod[ 'fields' ] as $k => $field ) {
                    if ( isset( $field[ 'id' ] ) )
                        unset( $pod[ 'fields' ][ $k ][ 'id' ] );
                }

                $api->save_pod( $pod );

                if ( !isset( $found[ 'pods' ] ) )
                    $found[ 'pods' ] = array();

                $found[ 'pods' ][ $pod[ 'name' ] ] = $pod[ 'label' ];
            }
        }

        if ( isset( $data[ 'templates' ] ) && is_array( $data[ 'templates' ] ) ) {
            foreach ( $data[ 'templates' ] as $template_data ) {
                if ( isset( $template_data[ 'id' ] ) )
                    unset( $template_data[ 'id' ] );

                $template = $api->load_template( array( 'name' => $template_data[ 'name' ] ) );

                if ( !empty( $template ) ) {
                    // Delete Template if it exists
                    if ( $replace ) {
                        $api->delete_template( array( 'id' => $template[ 'id' ] ) );

                        $template = array();
                    }
                }
                else
                    $template = array();

                $template = array_merge( $template, $template_data );

                $api->save_template( $template );

                if ( !isset( $found[ 'templates' ] ) )
                    $found[ 'templates' ] = array();

                $found[ 'templates' ][ $template[ 'name' ] ] = $template[ 'name' ];
            }
        }

        // Backwards compatibility
        if ( isset( $data[ 'pod_pages' ] ) ) {
            $data[ 'pages' ] = $data[ 'pod_pages' ];

            unset( $data[ 'pod_pages' ] );
        }

        if ( isset( $data[ 'pages' ] ) && is_array( $data[ 'pages' ] ) ) {
            foreach ( $data[ 'pages' ] as $page_data ) {
                if ( isset( $page_data[ 'id' ] ) )
                    unset( $page_data[ 'id' ] );

                $page = $api->load_page( array( 'name' => pods_var_raw( 'name', $page_data, pods_var_raw( 'uri', $page_data ), null, true ) ) );

                if ( !empty( $page ) ) {
                    // Delete Page if it exists
                    if ( $replace ) {
                        $api->delete_page( array( 'id' => $page[ 'id' ] ) );

                        $page = array();
                    }
                }
                else
                    $page = array();

                // Backwards compatibility
                if ( isset( $page_data[ 'uri' ] ) ) {
                    $page_data[ 'name' ] = $page_data[ 'uri' ];

                    unset( $page_data[ 'uri' ] );
                }

                if ( isset( $page_data[ 'phpcode' ] ) ) {
                    $page_data[ 'code' ] = $page_data[ 'phpcode' ];

                    unset( $page_data[ 'phpcode' ] );
                }

                $page = array_merge( $page, $page_data );

                $page[ 'name' ] = trim( $page[ 'name' ], '/' );

                $api->save_page( $page );

                if ( !isset( $found[ 'pages' ] ) )
                    $found[ 'pages' ] = array();

                $found[ 'pages' ][ $page[ 'name' ] ] = $page[ 'name' ];
            }
        }

        if ( isset( $data[ 'helpers' ] ) && is_array( $data[ 'helpers' ] ) ) {
            foreach ( $data[ 'helpers' ] as $helper_data ) {
                if ( isset( $helper_data[ 'id' ] ) )
                    unset( $helper_data[ 'id' ] );

                $helper = $api->load_helper( array( 'name' => $helper_data[ 'name' ] ) );

                if ( !empty( $helper ) ) {
                    // Delete Helper if it exists
                    if ( $replace ) {
                        $api->delete_helper( array( 'id' => $helper[ 'id' ] ) );

                        $helper = array();
                    }
                }
                else
                    $helper = array();

                // Backwards compatibility
                if ( isset( $helper_data[ 'phpcode' ] ) ) {
                    $helper_data[ 'code' ] = $helper_data[ 'phpcode' ];

                    unset( $helper_data[ 'phpcode' ] );
                }

                if ( isset( $helper_data[ 'type' ] ) ) {
                    if ( 'before' == $helper_data[ 'type' ] )
                        $helper_data[ 'type' ] = 'pre_save';
                    elseif ( 'after' == $helper_data[ 'type' ] )
                        $helper_data[ 'type' ] = 'post_save';
                }

                $helper = array_merge( $helper, $helper_data );

                if ( isset( $helper[ 'type' ] ) ) {
                    $helper[ 'helper_type' ] = $helper[ 'type' ];

                    unset( $helper[ 'helper_type' ] );
                }

                $api->save_helper( $helper );

                if ( !isset( $found[ 'helpers' ] ) )
                    $found[ 'helpers' ] = array();

                $found[ 'helpers' ][ $helper[ 'name' ] ] = $helper[ 'name' ];
            }
        }

        if ( !empty( $found ) )
            return $found;

        return false;
    }

    /**
     * Export a Package
     *
     * $params['pods'] string|array|bool Pod IDs to export, or set to true to export all
     * $params['templates'] string|array|bool Template IDs to export, or set to true to export all
     * $params['pages'] string|array|bool Page IDs to export, or set to true to export all
     * $params['helpers'] string|array|bool Helper IDs to export, or set to true to export all
     *
     * @param array $params Array of things to export
     *
     * @return array|bool
     *
     * @static
     * @since 2.0.5
     */
    public static function export ( $params ) {
        $export = array(
            'meta' => array(
                'version' => PODS_VERSION,
                'build' => time()
            )
        );

        $api = pods_api();

        $pod_ids = pods_var_raw( 'pods', $params );
        $template_ids = pods_var_raw( 'templates', $params );
        $page_ids = pods_var_raw( 'pages', $params );
        $helper_ids = pods_var_raw( 'helpers', $params );

        if ( !empty( $pod_ids ) ) {
            $params = array();

            if ( true !== $pod_ids )
                $params = array( 'ids' => $pod_ids );

            $export[ 'pods' ] = $api->load_pods( $params );
        }

        if ( !empty( $template_ids ) ) {
            $params = array();

            if ( true !== $template_ids )
                $params = array( 'ids' => $template_ids );

            $export[ 'templates' ] = $api->load_templates( $params );
        }

        if ( !empty( $page_ids ) ) {
            $params = array();

            if ( true !== $page_ids )
                $params = array( 'ids' => $page_ids );

            $export[ 'pages' ] = $api->load_pages( $params );
        }

        if ( !empty( $helper_ids ) ) {
            $params = array();

            if ( true !== $helper_ids )
                $params = array( 'ids' => $helper_ids );

            $export[ 'helpers' ] = $api->load_helpers( $params );
        }

        if ( 1 == count( $export ) )
            return false;

        $export = json_encode( $export );

        return $export;
    }
}