<?php
/*
Plugin Name: SEO Content Control
Plugin URI: http://www.linkstrasse.de/en/seo-content-control
Description: Onpage SEO tool. You and your authors get a powerful console to identify and resolve weak or missing pieces of content. The administration console is located in the administration menu "Tools": <a href="tools.php?page=seo-content-control/seo_content_control.php">Administration Console</a> | Amazon tips <a href="http://astore.amazon.com/linkstrasse-20">english</a>/<a href="http://astore.amazon.de/linkstrasse-21">deutsch</a>
Author: Martin Schwartz
Version: 1.1.0
Author URI: http://www.linkstrasse.de/en/
*/

/* 
   Copyright 2011  Martin Schwartz  ( web: http://www.linkstrasse.de/en/ )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function seo_content_control_l10domain() { return "seo_content_control"; } // our localization domain

/*
 * SeoContentControl
 *
 * Keep track of missing or weak content in posts, pages, categories, tags and so on.
 *
 * See also: _PHP4_COMPAT_
 */
class SeoContentControl {

    var $Utils = null;

    function RELEASENUM() {
        return "1.0.7";
    }

    function RELEASE() {
        return sprintf(
            __("SeoContentControl %s by Martin Schwartz of linkstrasse.de", seo_content_control_l10domain()),
            $this->RELEASENUM()
        );
    }

    function getUtils() {
        if ( !$this->Utils ) {
            $this->Utils = new SeoContentControlUtils( );
        }
        return $this->Utils;
    }

    function get_post_values ( ) {
        global $_POST;
        $params = $this->get_param_defs();
        $Utils = $this->getUtils();
        $values = array();
        if ( !empty($params) && is_array($params) ) {
            foreach ( array_keys($params) as $key ) {
                $param = $params[$key];
                $formkey = $param[0];
                $allowedvalues = $param[1];
                if ( $allowedvalues[1]=="{I}" ) {
                    $default = $allowedvalues[0];
                    $min = $allowedvalues[2];
                    $max = $allowedvalues[3];
                    if ( $okval = $Utils->integer_get( $_POST[$formkey], $min, $max) ) {
                        $values[$key] = $okval;
                    }       
                } else {
                    if ( $okval = $Utils->in_array_get( $_POST[$formkey], $allowedvalues ) ) {
                        $values[$key] = $okval;
                    }
                }
            }
        }
        return $values;
    }

    function get_param_defs () { }

    function _values ( $name, $user_id=0 ) {
        $values = array();
        if ( !$user_id ) {
            $user_id = $this->get_current_user_id();
        }
        if ( $user_id ) {
            if ( function_exists('get_user_meta') ) { /* wp3.0 */
                $values = get_user_meta( $user_id, $name, true );
            } elseif ( function_exists('get_usermeta') ) {
                $values = get_usermeta( $user_id, $name );
            }
            if ( empty($values) ) {
                $values = array();
            }
            if ( $this->fill_in_defaults( $values ) ) {
                // $this->update( $user_id, $values );
            }
        }
        return $values;
    }

    /*
     * Returns true, if something was added to $values.
     */
    function fill_in_defaults ( &$values ) {
        $updated = false;
        $params = $this->get_param_defs();
        if ( !empty($params) && is_array($params) ) {
            foreach ( array_keys($params) as $key ) {
                if ( empty($values[$key]) ) {
                    $param = $params[$key];
                    $allowedvalues = $param[1];
                    $defaultvalue = $allowedvalues[0]; # the first value is the default
                    if ( $key && $defaultvalue ) {
                        $values[$key] = $defaultvalue;
                        $updated = true;
                    }
                }
            }
        }
        return $updated;
    }

}

/*
 * SeoContentControlUtils
 */
class SeoContentControlUtils {

    var $has_form_start = false;
    var $has_form_end = false;

    //
    // Users
    //

    function param_for_user () {
        return 'seocc_user';
    }

    function get_current_user_id () {
        $id = 0;
        $current_user = wp_get_current_user(); 
        if ( $current_user ) {
            $id = $current_user->{'ID'};
            if ( !$id ) {
                $id = $current_user->{'user_id'};
            }
        }
        return $id;
    }

    function can_select_other_users ( $user_id ) {
        if ( $user_id && current_user_can('edit_others_posts',$user_id) ) {
            return true;
        } else {
            return false;
        }
    }

    function get_user_selectbox ( $withall=false, $current="", $as_table_row=false ) {
        $d = "";
        $users = $this->get_user_list( $withall );
        $current_user_id = $this->get_current_user_id();
        $can_select_other_users = $this->can_select_other_users( $current_user_id );
        if ( $can_select_other_users ) {
            $formname = "seocc_douser";
            $u_all = $users['all'];
            $u_ordered = $users['ordered'];

            $label = __("Current user", seo_content_control_l10domain());

            $s = "";
            $s .= '<select name="'.$this->param_for_user().'" onchange="this.form.submit()">';
            if ( !empty($u_ordered) && is_array($u_ordered) ) {
                foreach ( $u_ordered as $u ) {
                    if ( ($u['id']==$current_user_id) || $can_select_other_users ) {
                        $selected = "";
                        if ( $current && ($u['id']==$current) ) {
                            $selected = ' selected="selected"';
                        }
                        $s .= '<option value="'.$u['id'].'"'.$selected.'>&nbsp;'.$u['name'].' &nbsp;</option>';
                    }
                }
            }
            $s .= "</select>";
            if ( !$as_table_row ) {
                $s .= " ";
                $s .= $this->get_noscript_submitkey();
            }

            $description = __("Which user's texts should be analyzed?", seo_content_control_l10domain() );

            $d = $this->get_string_or_table_row( $label, $s, $as_table_row, $description );
        }
        return $d;
    }

    function get_selected_uservalue ( $withall=false, $default="" ) {
        $users = $this->get_user_list( $withall );
        $value = "";
        if ( is_array($users) ) {
            $userids = array_keys( $users['all'] );
            $value = $this->in_array_get( $_POST[$this->param_for_user()], $userids, $default );
        }
        return $value;
    }

    function get_user_list ( $withall=false ) {
        $current_user = wp_get_current_user(); 
        $users = get_users_of_blog();
        $u = array( 'all'=>array(), 'ordered'=>array() );
        if ( $withall ) {
            $name = __("All users", seo_content_control_l10domain());
            $u['all']['*'] = $name;
            $u['ordered'][] = array( 'id'=>"*", 'name'=>$name );
        }
        if ( !empty($users) && is_array($users) ) {
            foreach ( $users as $user ) {
                $user_id = $user->{'ID'}; /* wp3.0 */
                if ( !$user_id ) {
                    $user_id = $user->{'user_id'};
                }
                $name = $user->{'display_name'};
                if ( $user_id && $name ) {
                    $u['all'][$user_id] = $name;
                    $u['ordered'][] = array( 'id'=>$user_id, 'name'=>$name );
                }
            }
        }
        return $u;
    }

    //
    // post_status
    //

    function param_for_post_status() {
        return 'seocc_post_status';
    }

    function validate_post_status( $post_status ) {
        $keys = array( "publish", "private", "future", "pending", "auto-draft", "*" );
        return $this->in_array_get( $post_status, $keys, "publish" );
    }

    function get_current_post_status() {
        global $_POST;
        $formkey = $this->param_for_post_status();
        return $this->validate_post_status( $_POST[$formkey] );
    }

    function get_post_status_selectbox ( $current_post_status="", $as_table_row ) {
        $param_name = $this->param_for_post_status();
        if ( empty($current_post_status) ) {
            $current_post_status = $this->get_current_post_status();
        }
        $s = "";
        $possible_stati = array(
            "publish"    => __("Published"),
            "private"    => __("Privately Published"),
            "future"     => __("Scheduled"),
            "pending"    => __("Pending Review"),
            "auto-draft" => __("Draft"),
            "*"          => __("All Articles", seo_content_control_l10domain()),
            //"draft"      => __("Draft"),
        );
        $keys = array_keys( $possible_stati );

        $label = __('Article Status',seo_content_control_l10domain());

        $s = "";
        $s .= '<select name="'.$param_name.'" onchange="this.form.submit()">';
        if ( !empty($keys) && is_array($keys) ) {
            foreach ( $keys as $status ) {
                $desc = $possible_stati[$status];
                if ( $status && $desc ) {
                    $selected = "";
                    if ( $status == $current_post_status ) {
                        $selected = ' selected="selected"';
                    }
                    $s .= '<option value="'.$status.'"'.$selected.'>'.$desc.'&nbsp; </option>';
                }
            }
        }
        $s .= "</select>";
        $s .= " ";

        if ( !$as_table_row ) {
            $s .= $this->get_noscript_submitkey();
        }

        $description = __("Show only published articles?", seo_content_control_l10domain() );

        return $this->get_string_or_table_row( $label, $s, $as_table_row, $description );
    }

    //
    // post_type
    //

    function validate_post_type( $post_type ) {
        $keys = array( "post", "page" );
        return $this->in_array_get( $post_type, $keys, "post" );
    }

    function summary_row ( $link="", $row, $absref, $percent1, $percent2 ) {
        $s = "";
        if ( is_wp_error($row) ) {
            $s .= $this->summary_status_row ( $link, $row->get_error_code() );
        } elseif ( empty($row) ) {
            $s .= $this->summary_status_row ( $link, __("None available.",seo_content_control_l10domain()) );
        } else {
            $s .= "<tr>";
            $s .= "<td>$link</td>";
            $s .= $this->get_red_yellow( $row['all_info'], $absref, $percent1, $percent2, __("You seem to need more texts") );
            $s .= $this->get_green_yellow( $row['no_info'], $row['all_info'], $percent1, $percent2, __("Too much content is completely missing") );
            $s .= $this->get_green_yellow( $row['short_info'], $row['all_info'], $percent1, $percent2, __("Too much content seems a bit poor") );
            $s .= "</tr>";
        }
        return $s;
    }

    function summary_status_row ( $link="", $status="" ) {
        $s = "";
        $s .= "<tr>";
        $s .= "<td>$link</td>";
        $s .= "<td colspan=\"3\">$status</td>";
        $s .= "</tr>";
        return $s;
    }

    function summary_no_row_31 ( $s1, $s2 ) {
        $s = "";
        $s .= '<tr style="border:0;">';
        $s .= '<td style="border:0;" colspan="1">&nbsp;</td>';
        $s .= '<td style="border:0;" colspan="2">'.$s1.'</td>';
        $s .= '<td style="border:0;" colspan="1">'.$s2.'</td>';
        $s .= "</tr>";
        return $s;
    }

    //
    //
    //

    function fill_description_info ( &$a ) {
        if ( !empty($a) && is_array($a) ) {
            foreach ( array('all','no','short') as $t ) {
                $num = count( $a[$t] );
                $a[$t."_info"] = "$num";
            }
        }
    }

    function make_edit_link( $url="", $title="" ) {
        $edit = "";
        if ( $url ) {
            $edit = "<a href=\"$url\"$title>".__("Edit")."</a>";
        }
        return $edit;
    }

    function in_array_get ( $val, $values, $default="" ) {
        $result = "";
        if ( $val && $values ) {
            if ( in_array($val,$values) ) {
                $result = $val;
            }
        }
        if ( $result=="" ) {
            $result = $default;
        }
        return $result;
    }

    function in_parens ( $elements=array() ) {
        $a = array();
        $s = "";
        if ( !empty($elements) && is_array($elements) ) {
            foreach ( $elements as $e ) {
                if ( !empty($e) ) {
                    $a[] = $e;
                }
            }
        }
        if ( count($a) ) {
            $s = " (" . implode( ", ", $a ) . ")";
        }
        return $s;
    }

    /*
     *  A toggleable panel.
     */
    function get_panel ( $id="", $title="", $description="", $closable=true ) {
        $h3 = "";
        $classes = "";
        if ( $closable ) {
            $h3 = ""
                . '<div class="panel-top-arrow"><br /></div>'
                . '<h3 id='.$id.'>'.$title.'<span class="removing-panel">'.__('Minimize', seo_content_control_l10domain()).'<span></span></span></h3>'
            ;
            $classes = " closable";
        } else {
            $h3 = '<h3 id="'.$id.'">'.$title.'</h3>';
        }
        return ""
            . '<div class="panel-wrapper">' 
                . '<div class="panel panel-frame'.$classes.'">'
                    . '<div class="panel-top">'
                        . $h3
                    . '</div>'
                    . '<div class="panel-body">'
                        . $description
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<br class="clear">'
        ;
    }

    //
    // Functions to visualize the degree of incompleteness, TODO
    //

    function get_green_yellow( $val=0, $ref=0, $percent_green=20, $percent_yellow=70, $txtred="", $txtyellow="", $txtgreen="" ) {
        $class = "";
        $title = "";
        if ( !$val || $val<=($ref*1.0*$percent_green/100.0) ) {
            $class = "green";
            $title = $txtgreen;
        } elseif ( !$val || $val<=($ref*1.0*$percent_yellow/100.0) ) {
            $class = "yellow";
            $title = $txtyellow;
        } else {
            $class = "red";
            $title = $txtred;
        }
        return $this->get_rgb_class( $val, $class, $title );
    }

    function get_red_yellow( $val=0, $ref=0, $percent_red=20, $percent_yellow=70, $txtred="", $txtyellow="", $txtgreen="" ) {
        $class = "";
        $title = "";
        if ( $ref && (!$val || $val<=($ref*1.0*$percent_red/100.0)) ) {
            $class = "red";
            $title = $txtred;
        } elseif ( $ref && ($val<=($ref*1.0*$percent_yellow/100.0)) ) {
            $class = "yellow";
            $title = $txtyellow;
        } else {
            $class = "green";
            $title = $txtgreen;
        }
        return $this->get_rgb_class( $val, $class, $title );
    }

    function get_rgb_class( $val, $class, $title ) {
        if ( $class ) {
            $class = " class=\"$class\"";
        }
        if ( $title ) {
            $val = "<label title=\"$title\">$val&nbsp;&nbsp;</label>";
        }
        return "<td$class>$val</td>";
    }

    //
    // WordPress utils
    //

    function find_plugin_path( $plugin_name, $plugin_path_orig ) {
        $p = "";
        $plugins = get_plugins();
        if ( $plugins[$plugin_path_orig] ) {
            $p = $plugin_path_orig;
        } else {
            if ( !empty($plugins) && is_array($plugins) ) {
                foreach ( $plugins as $path => $plugin ) {
                    if ( $plugin_name == $plugin['Name'] ) {
                        $p = $path;
                        break;
                    }
                }
            }
        }
        return $p;
    }

    function integer_get ( $val, $min, $max ) {
        $i = "";
        $v = intval($val);
        $ok = true;
        if ( $ok && isset($min) ) {
            if ( $v < intval($min) ) {
                $ok = false;
            }
        }
        if ( $ok && isset($max) ) {
            if ( $v > intval($max) ) {
                $ok = false;
            }
        }
        if ( $ok ) {
            $i = $v;
        }
        return $i;
    }

    //
    // form utils
    //

    function get_form_start( $formname="" ) {
        $s = "";
        if ( !$this->has_form_start ) {
            $this->has_form_start = true;
            $s .= '<form action="" method="post">';
            $s .= wp_nonce_field( "", "nonce_$formname", false, false );
        }
        return $s;
    }

    function get_settings_form_name () {
        return "seocc_settings";
    }

    function get_form_end() {
        $s = "";
        if ( !$this->has_form_end ) {
            $this->has_form_end = true;
            $s .= '</form>';
        }
        return $s;
    }

    function get_form_row ( $label, $data, $description=" " ) {
        $s = "";
        $s .= '<tr><td style="padding-right:1em;">'."$label:".'</td><td style="padding-right:1em;">'.$data.'</td>';
        $s .= "<td> $description</td>";
        $s .= "</tr>";
        return $s;
    }

    function get_string_or_table_row( $label, $form, $as_table_row, $description="" ) {
        $s = "";
        if ( $as_table_row ) {
            $s = $this->get_form_row( $label, $form, $description );
        } else {
            $s .= "<p>".$label.": ".$form."</p>";
        }
        return $s;
    }

    function get_noscript_submitkey( $formname="" ) {
        $name = "";
        if ( $formname ) {
            $name = " name=\"$formname\"";
        }
        return '<noscript><input'.$name.' class="button-secondary action" type="submit" value="'.__("Apply").'"/></noscript>';
    }


}


/*
 *
 * SeoContentControlParameters
 *
 */
class SeoContentControlParameters extends SeoContentControl {

    var $param_defs = null;
    var $tools = null;

    function SeoContentControlParameters() { /* php4 */
        $this->__construct();
    }

    function __construct () {
        // params are defined e.g. via register_param_defs in SeoContentControlDescriptionToolBase implementations
        $this->param_defs = array(); 
        $this->tools = array(); 
    }

    function get_param_defs () {
        return $this->param_defs;
    }

    function add_param_def ( $param_key, $param_def ) {
        if ( !empty($param_key) && !empty($param_def) && is_array($param_def) && count($param_def)==4 ) {
            $this->param_defs[$param_key] = $param_def;
        }
    }

    function add_tool ( &$tool ) {
        $this->tools[] = $tool;
    }

    function get_tools ( ) {
        return $this->tools;
    }

    function values ( $user_id=0 ) {
        $Utils = $this->getUtils();
        if ( !$user_id ) {
            $user_id = $Utils->get_current_user_id();
        }
        return $this->_values( "seocc_params", $user_id );
    }

    function update ( $user_id, $values=array() ) {
        global $wpdb; 
        if ( $user_id && current_user_can('publish_posts',$user_id) ) {
            if ( !empty($values) ) {
                if ( function_exists('update_user_meta') ) { /* wp3.0 */
                    update_user_meta( $user_id, 'seocc_params', $wpdb->escape($values) );
                } elseif ( function_exists('update_usermeta') ) {
                    $meta_value = stripslashes_deep($values);
                    update_usermeta( $user_id, 'seocc_params', $meta_value );
                }
            }
        }
    }

    function display_form ( $user_id=0 ) {
        echo $this->get_form( $user_id );
    }

    function handle_post ( ) {
        global $_POST;
        $posted = false;
        $Utils = $this->getUtils();
        $formname = $Utils->get_settings_form_name();
        if (isset($_POST[$formname])) {
            $noncename = "nonce_$formname";
            $nonce = $_POST[$noncename];
            if ( !wp_verify_nonce($nonce, '')) die ( 'Security Check - If you receive this in error, log out and back in to WordPress');
            $this->update( $Utils->get_current_user_id(), $this->get_post_values() );
            $posted = true;
        }
        return $posted;
    }

    function get_form ( $user_id=0, $extra_forms="" ) {
        $s = "";
        $Utils = $this->getUtils();
        if ( !$user_id ) {
            $user_id = $Utils->get_current_user_id();
        }
        $canEdit = false;
        if ( $user_id && current_user_can('publish_posts',$user_id) ) {
            $canEdit = true;
        }
        if ( true ) {
            $formname = $Utils->get_settings_form_name();
            $values = $this->values();
            $s .= "<h4 style=\"margin-top:0\">".__("Settings")."</h4>";
            #$s .= wp_nonce_field( "", "nonce_$formname", false, false );
            $s .= '<table>';
            $params = $this->get_param_defs();
            $keys = array_keys( $params );
            if ( $extra_forms ) {
                $s .= $extra_forms;
            }
            if ( !empty($keys) && is_array($keys) ) {
                foreach ( $keys as $key ) {
                    if ( $key ) {
                        $param = $params[$key];
                        $name = $param[0];
                        $short = $param[2];
                        $long = $param[3];
                        $val = $values[$key];
                        if ( $name && $short && $long && $val ) {
                            $form = "";
                            if ( $canEdit ) {
                                $form = '<input type="text" name="'.$name.'" size="5" maxlength="5" value="'.$val.'">';
                            } else {
                                $form = $val;
                            }
                            $s .= $Utils->get_form_row( $short, $form, $long );
                        }
                    }
                }
            }
            $s .= '</table>';
            if ( $canEdit ) {
                $s .= '<input style="margin-top:1em;" id="'.$formname.'" type="submit" name="'.$formname.'" value="'.__("Apply").'"/>';
                $s .= '<br>';
            } else {
                $s .= '<p>';
                $s .= __("Please note that you need higher WordPress privileges to change these values.");
                $s .= '</p>';
            }
        }
        return $s;
    }

}

/*
 * SeoContentControlDescriptionTool
 *
 * Show missing or non optimal descriptions for tags, categories, authors and links.
 */
class SeoContentControlDescriptionTool extends SeoContentControl {
    function add_menu () {
        if ( function_exists('add_submenu_page') ) {
            $page = add_submenu_page(
                // 'options-general.php', // parent_slug for Settings 
                'tools.php', // parent_slug for Tools
                __("SEO Content Control: missing or weak content", seo_content_control_l10domain()), // page_title
                __("SEO Content Ctrl", seo_content_control_l10domain()), // menu_title
                "edit_posts", // capability
                __FILE__, // menu_slug
                'seo_content_control_descriptiontool_show_menu' // function
            );
            add_action("admin_print_scripts-$page", 'seo_content_control_descriptiontool_print_scripts');
            add_action("admin_print_styles-$page", 'seo_content_control_descriptiontool_print_styles');
        }
    }

    function show_menu () {
        // See also: http://codex.wordpress.org/Adding_Administration_Menus#Using_add_submenu_page 
        // See also: example from /options-discussion.php 

        $Utils = $this->getUtils();

        if ( !current_user_can('edit_posts')) {
            wp_die( __('You do not have sufficient permissions to access this page.') );
        }

        // add_contextual_help(...)

        $withall = true;
        $current_user_id = $Utils->get_current_user_id();
        $selected_user = $Utils->get_selected_uservalue( $withall, $current_user_id );

        $Params = new SeoContentControlParameters();

        // register all tools at $Params:
        $Params->add_tool( new SeoContentControlDescriptionToolArticle( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolPage( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolAuthor( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolCategory( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolTag( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolAioseop( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolWpseo( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolPlatinumseo( $Utils, $Params ) );
        $Params->add_tool( new SeoContentControlDescriptionToolCanSelect( $Utils, $Params, $withall ) );
        $Params->add_tool( new SeoContentControlDescriptionToolDonate( $Utils, $Params ) );

        //
        // If you like to add your own Tool:
        // 
        // add_action( 'seo_content_control_tools', 'your_plugin_function', 10, 2 );
        // function your_plugin_function( &$Utils, &$Params ) {
        //     $Params->add_tool( new Your_SeoContentControlDescriptionToolBase_Class($Utils,$Params) );
        // }
        //
        do_action( 'seo_content_control_tools', $Utils, $Params );

        $tools = $Params->get_tools();

        $has_posted = $Params->handle_post();
        if ( $has_posted ) {
            echo ""
                . '<div class="updated"><p><strong>'
                . __('Settings saved.', seo_content_control_l10domain())
                . '</strong></p></div>'
            ;
        }

        // When the tools are complete:
        $myparams = $Params->values(); 

        echo '<div class="wrap">'; 
        screen_icon();

        echo '<h2>'.__("Seo Content Control", seo_content_control_l10domain()).'</h2>';
        echo ""
            . '<div class="authorline">'
            . sprintf(__('Version %s',seo_content_control_l10domain()), $this->RELEASENUM() )
            . " "
            . __('by Martin Schwartz - <a href="http://www.linkstrasse.de/en/seo-content-control">Plugin Home</a>', seo_content_control_l10domain())
            . '</div>'
        ;

        if ( true ) {
            $id = "status";
            $title = __("Missing Or Weak Content", seo_content_control_l10domain());
            $d = "";
            
            $extraforms = array();
            $cannotinfo = array();
            $td = '<td>';
            $th = '<th>';
            $d .= '<table style="border-collapse:collapse;" id="description-summary">';
            $d .= "<tr>".$th.__("Content type",seo_content_control_l10domain())."</th>".$th.__("Number of texts",seo_content_control_l10domain())."</th>".$th.__("Missing texts",seo_content_control_l10domain())."</th>".$th.__("Weak texts",seo_content_control_l10domain())."</th></tr>";
            if ( !empty($tools) && is_array($tools) ) {
                foreach ( $tools as $tool ) {
                    if ( $tool ) {
                        if ( $tool->can() ) {
                            $d .= $tool->get_summary_row( $selected_user, $myparams );
                            $extraforms[] = $tool->get_extra_forms( $selected_user, $params );
                        } else {
                            $cannot = $tool->get_cannot_info();
                            if ( $cannot ) {
                                $cannotinfo[] = $cannot;
                            }
                        }
                    }
                }
            }
            $d .= "</table>";

            $form_begin = $Utils->get_form_start( $Utils->get_settings_form_name() );
            $form_end = $Utils->get_form_end();
            $extraforms[] = $Utils->get_post_status_selectbox("",true);
            $forms = $Params->get_form(0, implode(" ",$extraforms));

            if ( $forms ) {
                $d .= $form_begin . $forms . $form_end;
            }

            $d .= "<br>";

            if ( !empty($cannotinfo) && is_array($cannotinfo) ) {
                $d .= "<p>". __("Please note that for further SEO optimizations your user account has not enough privileges:",seo_content_control_l10domain())."</p>"."<ol>";
                foreach ( $cannotinfo as $info ) {
                    $d .= "<li>$info</li>";
                }
                $d .= "</ol><br>";
            }

            echo $Utils->get_panel( $id, $title, $d, false );
        }

        $this->show_intro_text();

        if ( !empty($tools) && is_array($tools) ) {
            foreach ( $tools as $tool ) {
                if ( !empty($tool) && $tool->can() ) {
                    echo $tool->get_details( $selected_user, $myparams );
                }
            }
        }
        echo "<br>";

        echo '</div>';
    }

    function show_intro_text () {
        $Utils = $this->getUtils();
        $description = __( ""
            . "<p>In WordPress you should present each article in three different versions:</p> "
            . "<ol>"
            . "<li>The article itself. It should be rather long. "
                . "I'd recommend a minimum length of about 1500 characters. "
                . "Write a great article. Keep the users in mind! "
                . "Make your article the perfect match for a specific search query. "
                . "</li>"
            . "<li>The summary. It will be shown in the <em>XML feeds</em> and "
                . "in the <em>archive pages</em>. This summary should be quite long as well, I "
                . "suggest an absolute minimum of about 300 <em>characters</em>. "
                . "Create an interesting summary or alternate version of the article. "
                . "It should really be unique content. "
                . "</li>"
            . "<li>The meta description. Search engines show about 140 characters of the meta "
                . "description in the search results, if it fits well to the user's search query. "
                . "If the users see a snippet matching perfectly to their query they are more "
                . "likely to click on it. Think of searching for a specific word and then reading "
                . "your description. Is it good enough? "
                . "WordPress has no built in way to edit the meta description, but a number of "
                . "plugin can help out. "
                . "</li>"
            . "</ol>"
            . "<p>"
            . "Finally, when your're finished with the three versions of the article "
            . "you should give your blog another boost. "
            . "Care for good descriptions for the categories, tags and author "
            . "of the article. "
            . "</p>"
            . "<ol start=\"4\">"
            . "<li>Individualize the WordPress archives. "
            . "All of the archives of WordPress provide very similar content. You find the same text "
            . "snippets in tag archives, category archives, author archives, monthly archives and so on. "
            . "You should individualize each of these 'boring' archives "
            . "by adding unique content as good as you can. Add for each author, each tag and each "
            . "category a good and not too short description. "
            . "Please note that your WordPress account needs at least 'Editor' privileges to edit "
            . "tag and category descriptions. "
            . "</li>"
            . "</ol>"
            . "<p>"
        , seo_content_control_l10domain());
        echo $Utils->get_panel( "description", __("SEO Description Magic", seo_content_control_l10domain()), $description, true );
    }

}

class SeoContentControlDescriptionToolBase {

    var $Tool = null;
    var $data_arrays_fetched = false;
    var $data_arrays = null;
    var $data_arrays_error = "";
    var $can_fetched = false;
    var $can = false;

    function SeoContentControlDescriptionToolBase ( &$SeoTool, $Params ) { /* php4 */
        $this->__construct( $SeoTool, $Params ); 
    }

    function __construct ( &$SeoTool, $Params ) {
        $this->Tool = $SeoTool;
        $this->register_param_defs( $Params );
    }

    function getUtils () {
        return $this->Tool;
    }

    function get_arrays( $selected_user=0, &$myparams ) {
        if ( !$this->data_arrays_fetched ) {
            $this->data_arrays_fetched = true;
            $fetch_result = $this->fetch_arrays( $selected_user, $myparams );
            if ( is_wp_error($data_arrays) ) {
                $this->data_arrays_error = $fetch_result;
                $this->data_arrays = null;
            } else {
                $this->data_arrays_error = "";
                $this->data_arrays = $fetch_result;
            }
        }
        return $this->data_arrays;
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        return null;
    }

    function can () {
        if ( !$this->can_fetched ) {
            $this->can = $this->fetch_can();
            $this->can_fetched = true;
        }
        return $this->can;
    }

    function get_cannot_info () {
        return "";
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        return "";
    }

    function get_details ( $selected_user=0, &$myparams ) {
        return "";
    }

    function get_extra_forms ( $selected_user=0, &$myparams ) {
        return "";
    }

    function register_param_defs ( $Params ) {
    }

    function fetch_can () {
        return false;
    }

}

class SeoContentControlDescriptionToolDonate extends SeoContentControlDescriptionToolBase {

    function fetch_can () {
        return true;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {

        $Utils = $this->getUtils();
        $PP = __(""
            . '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">'
            . '<input type="hidden" name="cmd" value="_s-xclick">'
            . '<input type="hidden" name="hosted_button_id" value="VP3XESURS45X6">'
            . '<input type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online.">'
            . '<img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1">'
            . '</form>',
            seo_content_control_l10domain()
        );

        return $Utils->summary_no_row_31(
            '<span style="font-size:11px;color:#777;"><em>'
                . __('If you like this tool, I\'d appreciate a lot if you helped<br>with a donation to keep it actively developed:', seo_content_control_l10domain())
                . '</em></span>'
            ,
            '<div style="text-align:right;">'.$PP.'</div>'
        );
    }

}

class SeoContentControlDescriptionToolCanSelect extends SeoContentControlDescriptionToolBase {

    var $withall = false;

    function SeoContentControlDescriptionToolCanSelect ( &$SeoTool, $Params, $withall ) { /* php4 */
        $this->__construct( $SeoTool, $Params, $withall );
    }

    function __construct ( &$SeoTool, $Params, $withall ) {
        parent::__construct( $SeoTool, $Params );
        $this->withall = $withall;
    }

    function fetch_can () {
        $Utils = $this->getUtils();
        $current_user_id = $Utils->get_current_user_id();
        $can_select = false;
        if ( $Utils->can_select_other_users($current_user_id) ) {
            $can_select = true;
        }
        return $can_select;
    }

    function get_cannot_info () {
        return __("To select other users your account needs to be upgraded to 'Editor'.",seo_content_control_l10domain());
    }

    function get_extra_forms ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        return $Utils->get_user_selectbox( $this->withall, $selected_user, true );
    }
}

/**
 *
 *  AUTHOR handling
 *
 **/
class SeoContentControlDescriptionToolAuthor extends SeoContentControlDescriptionToolBase {

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "authorlen",
            array( "seocc_authorlen",   array(400,  '{I}', 1), __("Bio length",seo_content_control_l10domain()),      __("How long should a biographical info be? (default: 400 chars)",seo_content_control_l10domain()) )
        );
    }

    function fetch_can () {
        return true;
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        return $this->get_bad_author_descriptions( $selected_user, $myparams['authorlen'] );
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        $a = $this->get_arrays( $selected_user, $myparams );
        return $Utils->summary_row( 
            "<a href=\"#authordescriptions\">"
                . __("Authors",seo_content_control_l10domain())
                . "</a>"
                . ", "
                . __("Biographical info",seo_content_control_l10domain())
            ,
            $a, $a['all_info'], 1, 20
        );
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $a = $this->get_arrays( $selected_user, $myparams );
        return $this->get_bad_author_details ( &$a, $selected_user, $myparams['authorlen'] );
    }

    function get_bad_author_descriptions ( $selected_user="", $authorlen ) {
        $a = false;
        $Utils = $this->getUtils();
        $current_user_id = $Utils->get_current_user_id();
        $users = get_users_of_blog();
        if ( !empty($users) && is_array($users) ) {
            $a = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
            foreach ( $users as $user ) {
                $user_id = $user->{'ID'}; /* wp3.0 */
                if ( !$user_id ) {
                    $user_id = $user->{'user_id'};
                }
                if ( $selected_user!="*" ) {
                    if ( $selected_user && ($user_id != $selected_user) ) {
                        continue;
                    }
                }
                $name = $user->{'display_name'};
                $description = "";
                if ( function_exists('get_the_author_meta') ) { /* wp3.0 */
                    $description = get_the_author_meta( 'description', $user_id );
                } elseif ( function_exists('get_usermeta') ) {
                    $description = get_usermeta ( $user_id, 'description' );
                }
                $len = 0;
                if ( $description ) {
                    $len = strlen(trim($description));
                }
                $link = get_author_posts_url( $user_id );
                $editlink = "";
                if ( $current_user_id == $user_id ) {
                    $editlink = 'profile.php#description';
                } else {
                    $editlink = 'user-edit.php?user_id=' . $user_id . "#description";
                }
                $record = array( 'id'=>$user_id, 'name'=>$name, 'len'=>$len, 'link'=>$link, 'editlink'=>$editlink );
                $a['all'][] = $record;
                if ( !$len ) {
                    $a['no'][] = $record;
                } elseif ( $len<$authorlen ) {
                    $a['short'][] = $record;
                }
            }
            $Utils->fill_description_info( $a );
        }
        return $a;
    }

    function get_bad_author_details ( &$a, $selected_user="", $authorlen=300 ) {
        $Utils = $this->getUtils();
        $toggle = true;
        $id = "authordescriptions";
        $title = __("Author Profiles", seo_content_control_l10domain());
        $d = "";

        if ( !is_array($a) ) {
            $d .= __("Internal error when retrieving the biographical info of the authors!", seo_content_control_l10domain());
        } else {
            if ( empty($a['all']) ) {
                $d .=  __("There are currently no authors?!", seo_content_control_l10domain);
            } elseif ( !empty($a['no']) || !empty($a['short']) ) {
                $d .= "<p>";
                $d .= __("When clicking on an author's name WordPress shows an archive of "
                    . "all the posts of this author. At the beginning of the archive the "
                    . "theme should display the author's biographical info. "
                    . "Tell the users a bit about why and what kind of articles you write. ",
                    seo_content_control_l10domain()
                );
                $d .= "</p>";
                $d .= '<table><tr>';

                if ( !$selected_user || $selected_user=="*" ) {
                    $d .= '<td style="vertical-align:top;width:50%;">';
                    $d .= '<h4 style="margin-top:0;">'.__("Authors without biographical info", seo_content_control_l10domain()).'</h4>';
                    if ( empty($a['no']) ) {
                        $d .= '<p>'.__("No problem. All authors provide at least some short biographical infos.", seo_content_control_l10domain())."</p>";
                    } elseif ( !empty($a['no']) ) {
                        $d .= '<p>'.__("The following authors provide no biographical info:", seo_content_control_l10domain())."</p>";
                        $d .= $this->get_author_list( $a['no'], false);
                    }
                    $d .= "</td>";

                    $d .= '<td style="vertical-align:top;width:50%;">';
                    $d .= '<h4 style="margin-top:0;">'.__("Authors with too little biographical info", seo_content_control_l10domain()).'</h4>';
                    if ( empty($a['short']) ) {
                        $d .= '<p>'.__("All other authors give some sufficiently long biographical info.", seo_content_control_l10domain())."</p>";
                    } else {
                        $d .= '<p>'.sprintf(__("The biographical info of the authors below should be improved to %s characters or more:", seo_content_control_l10domain()), $authorlen)."</p>";
                        $d .= $this->get_author_list( $a['short'], true);
                    }
                    $d .= "</td>";
                } else {
                    $d .= '<td style="vertical-align:top;width:50%;">';
                    if ( !empty($a['no']) ) {
                        $d .= "<p style=\"margin-top:0;\">";
                        $d .= __("The biographical info is missing:", seo_content_control_l10domain());
                        $d .= "</p>";
                        $d .= $this->get_author_list( $a['no'], false);
                    } elseif ( !empty($a['short']) ) {
                        $d .= "<p style=\"margin-top:0;\">";
                        $d .= __("The biographical info is too short:", seo_content_control_l10domain());
                        $d .= "</p>";
                        $d .= $this->get_author_list( $a['short'], true);
                    } else {
                        $d .= "<p style=\"margin-top:0;\">";
                        $d .= __("The biographical info of the profile page is long enough:", seo_content_control_l10domain());
                        $d .= "</p>";
                        $d .= $this->get_author_list( $a['all'], false);
                    }
                    $d .= "</td>";
                }

                $d .= "</tr></table>";
                $toggle = true;
            } else {
                if ( $selected_user=="*" ) {
                    $d .= __("All authors provide a sufficient amount of biographical info.", seo_content_control_l10domain());
                } else {
                    $d .= __("The biographical info of the profile page is long enough.", seo_content_control_l10domain());
                }
                // Show the list anyway for convenience:
                $d .= $this->get_author_list( $a['all'], true);
            }
        }
        $d = "<p>$d</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

    function get_author_list( &$a, $showlen=false ) {
        $Utils = $this->getUtils();
        $s = "";
        if ( !empty($a) && is_array($a) ) {
            $s .= "<ol>\n";
            foreach ( $a as $element ) {
                $user_id = intval($element['id']);
                $name = $element['name'];
                $len = $element['len'];
                $link = $element['link'];
                $editlink = $element['editlink'];
                $edit = $Utils->make_edit_link( $editlink );
                if ( $user_id && $name && $link ) {
                    $s .= "<li><a href=\"$link\">$name</a>";
                    if ( $showlen ) {
                        $s .= " ($edit, $len ".__("chars", seo_content_control_l10domain()).")";
                    } else {
                        $s .= " ($edit)";
                    }
                    $s .= "</li>\n";
                }
            }
            $s .= "</ol>\n";
        }
        return $s;
    }

}

/**
 *
 *  Taxonomies (abstract class for tags and categories)
 *
 */
class SeoContentControlDescriptionToolTax extends SeoContentControlDescriptionToolBase {

    var $tax = "";

    function SeoContentControlDescriptionToolTax ( &$SeoTool, $Params, $taxonomy ) { /* php4 */
        $this->__construct( $SeoTool, $Params, $taxonomy );
    }

    function __construct ( &$SeoTool, $Params, $taxonomy ) {
        parent::__construct( $SeoTool, $Params );
        $this->tax = $taxonomy;
    }

    function getTax () {
        return $this->tax;
    }

    function fetch_can () {
        if ( current_user_can("manage_categories") ) {
            return true;
        } else {
            return false;
        }
    }

    function get_term_list( &$a, $showlen=false ) {
        $Utils = $this->getUtils();
        $taxonomy_name = $this->getTax();
        $d = "";
        if ( !empty($a) && is_array($a) ) {
            $d .= "<ol>\n";
            foreach ( $a as $element ) {
                $id = intval($element['id']);
                $name = $element['name'];
                $len = $element['len'];
                $link = $element['link'];
                $editlink = $element['editlink'];
                $edit = $Utils->make_edit_link( $editlink );
                $info = "";
                if ( !$editlink ) {
                    $info = "<em>read only</em>";
                }
                if ( $name && $link ) {
                    $s = "<li><a href=\"$link\">$name</a>";
                    if ( $showlen ) {
                        $s .= $Utils->in_parens(array( $info, $edit, "$len ".__("chars", seo_content_control_l10domain()) ));
                    } else {
                        $s .= $Utils->in_parens(array( $info, $edit ));
                    }
                    $s .= "</li>\n";
                    $d .= $s;
                }
            }
            $d .= "</ol>\n";
        }
        return $d;
    }

    function get_bad_tax_descriptions ( $taxonomy_name, $taxlen ) {
        $Utils = $this->getUtils();
        $a = false;
        $terms = get_terms ( $taxonomy_name );
        if ( is_wp_error($terms) ) {
            // oops!
            // echo "<p>Error: '".$terms->get_error_code()."'</p>";
            $a = $terms;
        } elseif ( empty($terms) ) {
            $a = array();
        } elseif ( is_array($terms) ) {
            $a = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
            foreach ( $terms as $term ) {
                $id = $term->{'term_id'};
                $name = $term->{'name'};
                if ( array_key_exists( 'description', $term ) ) {
                    $description = $term->{'description'};
                    $link = "";
                    $len = 0;
                    if ( $description ) {
                        $len = strlen( trim($description) );
                    }
                    if ( $id ) {
                        $editlink = get_edit_tag_link( $id, $taxonomy_name ); 
                        switch ( $taxonomy_name ) {
                            case "category": 
                                $link = get_category_link( $id, $taxonomy_name ); 
                                if ( preg_match('/\/edit-tags\.php/', $editlink ) ) { /* wp2.7 && wp3.0 */
                                    # http://www.test.test/wp-admin/categories.php?action=edit&cat_ID=1 /* wp2.7 real */
                                    # http://www.weiterbildung-infos.de/wp-admin/edit-tags.php?action=edit&tag_ID= /* wp2.7 false */
                                    # http://www.test.test/wp-admin/edit-tags.php?action=edit&taxonomy=category&post_type=post&tag_ID=1 /* wp3.0 */ 
                                    if ( !preg_match('/taxonomy=category/', $editlink ) ) {
                                        $editlink = preg_replace(
                                            '/\/edit-tags\.php.*/', 
                                            '/categories.php?action=edit&cat_ID='.$id,
                                            $editlink
                                        );
                                    }
                                }
                            break;
                            case "post_tag": 
                                $link = get_tag_link( $id, $taxonomy_name ); 
                            break;
                            default:
                        }
                    }
                    $record = array( 
                        'id'=>$id, 'name'=>$name, 'len'=>$len, 'desc'=>$description, 'link'=>$link, 'editlink'=>$editlink
                    );
                    $a['all'][] = $record;
                    if ( !$len ) {
                        $a['no'][] = $record;
                    } elseif ( $len<$taxlen ) {
                        $a['short'][] = $record;
                    }
                    $Utils->fill_description_info( $a );
                } else {
                    $a = false; /* no tag descriptions available in this version of wordpress? */
                    break;
                }
            }
        }
        return $a;
    }

}

/**
 *
 *  Tags
 *
 */
class SeoContentControlDescriptionToolTag extends SeoContentControlDescriptionToolTax {

    function SeoContentControlDescriptionToolTag ( &$SeoTool, $Params ) { /* php4 */
        $this->__construct( $SeoTool, $Params );
    }

    function __construct ( &$SeoTool, $Params ) {
        parent::__construct( $SeoTool, $Params, "post_tag" );
    }

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "taglen",
            array( "seocc_taglen",      array(400,  '{I}', 1), __("Tag length",seo_content_control_l10domain()),      __("How long should the description of a tag be? (default: 400 chars)",seo_content_control_l10domain())  )
        );
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        return $this->get_bad_tax_descriptions( 'post_tag', $myparams['taglen'] );
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        $a = $this->get_arrays( $selected_user, $myparams );
        return $Utils->summary_row( 
            "<a href=\"#tagdescriptions\">"
                . __("Tag Descriptions",seo_content_control_l10domain())."</td>"
                . "</a>"
            ,
            $a, 20, 20, 70
        );
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $a = $this->get_arrays( $selected_user, $myparams );
        return $this->get_tag_descriptions($a, $myparams['taglen']);
    }

    function get_cannot_info () {
        return __("To edit tag descriptions your account would need to be upgraded to 'Editor'.",seo_content_control_l10domain());
    }

    function get_tag_descriptions ( &$a, $taglen ) {
        $Utils = $this->getUtils();
        $id = "tagdescriptions";
        $title = __("Tag descriptions", seo_content_control_l10domain());
        $toggle = true;
        $d = "";
        if ( !is_array($a) ) {
            $d .= "<p>";
            $d .= __("Internal error when retrieving the tags!", seo_content_control_l10domain());
            $d .= "</p>";
        } else {
            $d .= __(""
                . "<p>"
                . "Most WordPress themes show a list of tags under a post or even a "
                . "tag cloud in the sidebar. If you click on a tag you come to an "
                . "archive page with article summaries of all posts with this tag. "
                . "You can improve your SEO onpage optimization, if you provide unique "
                . "individual content for each tag. "
                . "Describe the matter of the tag and tell your users, what kind of "
                . "articles they will find under this tag."
                . "</p>", seo_content_control_l10domain()
            );
            if ( empty($a['all']) ) {
                $d .= __("You have currently no tags defined.", seo_content_control_l10domain());
            } elseif ( !empty($a['no']) || !empty($a['short']) ) {
                $d .= '<table><tr>';

                $d .= '<td style="vertical-align:top;width:50%;">';
                $d .= '<h4 style="margin-top:0;">'.__("Tags with missing descriptions", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['no']) ) {
                    $d .= '<p>'.__("No problem. All tags have at least a short description.", seo_content_control_l10domain())."</p>";
                } elseif ( !empty($a['no']) ) {
                    $d .= '<p>'.sprintf(__("The following tags have no description at all. Each tag should be described with %s characters at the minimum:", seo_content_control_l10domain()), $taglen)."</p>";
                    $d .= $this->get_term_list( $a['no'], false);
                }
                $d .= "</td>";

                $d .= '<td style="vertical-align:top;width:50%;">';
                $d .= '<h4 style="margin-top:0;">'.__("Tags with weak descriptions", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['short']) ) {
                    $d .= '<p>'.__("No problem. All other tags have a sufficiently long description.", seo_content_control_l10domain())."</p>";
                } else {
                    $d .= '<p>'.sprintf(__("The tags below have a short description. Better use %s characters or more:", seo_content_control_l10domain()), $taglen)."</p>";
                    $d .= $this->get_term_list( $a['short'], true);
                }
                $d .= "</td>";

                $d .= "</tr></table>";
            } else {
                $d .= __("All tags have a description.", seo_content_control_l10domain());
            }
        }
        $d = "<p>$d</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

}

/**
 *
 *  Categories
 *
 */
class SeoContentControlDescriptionToolCategory extends SeoContentControlDescriptionToolTax {

    function SeoContentControlDescriptionToolCategory ( &$SeoTool, $Params ) {
        $this->__construct( $SeoTool, $Params );
    }

    function __construct ( &$SeoTool, $Params ) {
        parent::__construct( $SeoTool, $Params, "post_tag" );
    }

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "categorylen",
            array( "seocc_categorylen", array(400,  '{I}', 1), __("Category length",seo_content_control_l10domain()), __("How long should a description of a category be? (default: 400 chars)",seo_content_control_l10domain())  )
        );
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        return $this->get_bad_tax_descriptions( 'category', $myparams['categorylen'] );
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        $a = $this->get_arrays( $selected_user, $myparams );
        return $Utils->summary_row(
            "<a href=\"#categorydescriptions\">"
                . __("Category Descriptions",seo_content_control_l10domain())
                . "</a>"
            ,
            $a, 12, 20, 70
        );
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $a = $this->get_arrays( $selected_user, $myparams );
        return $this->get_category_descriptions($a, $myparams['categorylen']);
    }

    function get_cannot_info () {
        return  __("To edit category descriptions your account would need to be upgraded to 'Editor'.",seo_content_control_l10domain());
    }

    function get_category_descriptions ( &$a, $categorylen=400 ) {
        $Utils = $this->getUtils();
        $id = "categorydescriptions";
        $d = "";
        $toggle = true;
        $title = __("Category descriptions", seo_content_control_l10domain());

        if ( !is_array($a) ) {
            $d .= __("Internal error when retrieving the categories!", seo_content_control_l10domain());
        } elseif ( empty($a['all']) ) {
            $d .= __("You have currently defined no category.", seo_content_control_l10domain());
        } elseif ( !empty($a['no']) || !empty($a['short']) ) {
            $toggle = true;
            $d .= '<table><tr>';

            $d .= '<td style="vertical-align:top;width:50%;">';
            $d .= '<h4 style="margin-top:0;">'.__("Categories with missing descriptions", seo_content_control_l10domain()).'</h4>';
            if ( empty($a['no']) ) {
                $d .= '<p>'.__("No missing descriptions, all categories have at least a tiny description.", seo_content_control_l10domain())."</p>";
            } else {
                $d .= '<p>'.sprintf(__("The following categories have no description at all. You should describe each category with at least %s characters:", seo_content_control_l10domain()), $categorylen)."</p>";
                $d .= $this->get_term_list( $a['no'], false);
            }
            $d .= "</td>";

            $d .= '<td style="vertical-align:top;width:50%;">';
            $d .= '<h4 style="margin-top:0;">'.__("Categories with weak descriptions", seo_content_control_l10domain()).'</h4>';
            if ( empty($a['short']) ) {
                $d .= '<p>'.__("No problem. All other categories have a sufficiently long description.", seo_content_control_l10domain())."</p>";
            } else {
                $d .= '<p>'.sprintf(__("The categories listed below have a short description. Improve it by taking %s characters or more:", seo_content_control_l10domain()), $categorylen)."</p>";
                $d .= $this->get_term_list( $a['short'], true);
            }
            $d .= "</td>";

            $d .= "</tr></table>";
        } else {
            $d .= __("All categories have a description.", seo_content_control_l10domain());
        }
        $d = "<p>$d</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

}


/**
 *
 *  Posts (abstract class for articles, article descriptions and pages)
 *
 */
class SeoContentControlDescriptionToolPost extends SeoContentControlDescriptionToolBase {

    var $_post_type = "";
    var $_get_both = false;

    function SeoContentControlDescriptionToolPost ( &$SeoTool, $Params, $param_post_type, $param_get_both ) { /* php4 */
        $this->__construct( $SeoTool, $Params, $param_post_type, $param_get_both );
    }

    function __construct ( &$SeoTool, $Params, $param_post_type, $param_get_both ) {
        parent::__construct( $SeoTool, $Params );
        $this->_post_type = $param_post_type;
        $this->_get_both = $param_get_both;
    }

    function get_post_type () {
        return $this->_post_type;
    }

    function get_both () {
        return $this->_get_both;
    }

    function getType () {
        return $this->type;
    }

    function get_bad_articles_and_excerpts ( $selected_user="", $postlen, $excerptlen ) {
        $Utils = $this->getUtils();
        $get_both = $this->get_both();
        $a = false;
        if ( !$get_both ) {
            $excerptlen = 0;
        }
        $stats = $this->get_status_of_posts($selected_user, $Utils->get_current_post_status() );
        if ( is_wp_error($stats) ) {
            $a = $stats;
        } elseif ( empty($stats) ) {
            $a = array();
        } elseif ( is_array($stats) ) {
            $a_both  = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
            if ( $get_both ) {
                $a_content  = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
                $a_excerpt = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
            }
            foreach ( $stats as $stat ) {
                $id = $stat['ID'];
                $name = $stat['post_title'];
                $author = $stat['post_author'];
                $len1 = intval($stat['articlelen']);
                $len2 = intval($stat['excerptlen']);
                $link = get_permalink($id);
                $editlink = get_edit_post_link( $id, "url" );
                $record = array( 
                    'id'=>$id, 'author'=>$author, 'name'=>$name, 'len'=>$len1, 'len2'=>$len2,
                    'link'=>$link, 'editlink'=>$editlink
                );
                $a_both['all'][] = $record;
                $a_content['all'][] = $record;
                $a_excerpt['all'][] = $record;
                if ( $id && $name ) {
                    if ( ($postlen && !$len1) || ($excerptlen && !$len2) ) {
                        $a_both['no'][] = $record;
                        if ( $get_both ) {
                            if ( $postlen && !$len1 ) {
                                $a_content['no'][] = $record;
                            }
                            if ( $excerptlen && !$len2 ) {
                                $a_excerpt['no'][] = $record;
                            }
                        }
                    } elseif ( ($postlen && ($len1<$postlen)) || ($excerptlen && ($len2<$excerptlen)) ) {
                        $a_both['short'][] = $record;
                        if ( $get_both ) {
                            if ( $postlen && ($len1<$postlen) ) {
                                $a_content['short'][] = $record;
                            }
                            if ( $excerptlen && ($len2<$excerptlen) ) {
                                $a_excerpt['short'][] = $record;
                            }
                        }
                    }
                }
            }
            if ( $get_both ) {
                $Utils->fill_description_info( $a_both );
                $Utils->fill_description_info( $a_content );
                $Utils->fill_description_info( $a_excerpt );
                $a = array ( 'both'=>$a_both, 'content'=>$a_content, 'excerpt'=>$a_excerpt );
            } else {
                $Utils->fill_description_info( $a_both );
                $a = $a_both;
            }
        }
        return $a;
    }

    function get_status_of_posts ( $selected_user="", $post_status="publish" ) {
        // see also: wp-includes/post.php:wp_count_posts()
        global $wpdb;
        $results = null;
        $Utils = $this->getUtils();

        $post_status = $Utils->validate_post_status( $post_status );
        $post_type = $Utils->validate_post_type( $this->get_post_type() );
        $user_id = $Utils->get_current_user_id();
        $where_user = "";

        if ( $post_status && $post_type && $user_id ) {
            if ( $selected_user && ($selected_user!="*") ) {
                $where_user_and = "post_author='$selected_user' and ";
            }
            $post_status_query = "";
            if ( $post_status && $post_status!="*" ) {
                $post_status_query = "and post_status='$post_status' ";
            }
            $get_excerpt_len = "";
            if ( $post_type == "post" ) {
                $get_excerpt_len = ", length(trim(post_excerpt)) as excerptlen ";
            }
            if ( $user_id ) {
                $query = ""
                    . "select ID, post_author, post_title, "
                        . "length(trim(post_content)) as articlelen "
                        . $get_excerpt_len
                    . "from {$wpdb->posts} "
                    . "where "
                        . $where_user_and
                        . "post_type='".$post_type."' "
                        . $post_status_query
                    . "order by ID DESC "
                ;
                $results = $wpdb->get_results( $wpdb->prepare($query), ARRAY_A );
                if ( !is_wp_error($results) && empty($results) ) {
                    # return an empty array when no database rows are found
                    $results = array();
                }
            }
        }

        return $results;
    }

    function get_article_list( &$a, $showlen=false, $selected_user="", $articlelen, $excerptlen=0, $article_term ) {
        $Utils = $this->getUtils();
        $d = "";
        if ( !empty($a) && is_array($a) ) {
            $d .= "<ol>\n";
            foreach ( $a as $element ) {
                $id = intval($element['id']);
                $name = $element['name'];
                $author = $element['author'];
                $len_content = $element['len'];
                $len_excerpt = $element['len2'];
                $link = $element['link'];
                $editlink = $element['editlink'];
                if ( true && $author ) { # if ( $selected_user=="*" && $author ) {
                    $author_url = get_author_posts_url( $author );
                    if ( function_exists('get_the_author_meta') ) { /* wp3.0 */
                        $author_name = get_the_author_meta( 'display_name', $author );
                    } elseif ( function_exists('get_usermeta') ) {
                        $author_name = get_usermeta( $author, 'nickname' );
                    }
                    if ( $author_url && $author_name ) {
                        $author = __("by",seo_content_control_l10domain())." "."<a href=\"$author_url\">$author_name</a>";
                    }
                }

                $title = "";
                if ( $len_content<$articlelen && !($len_excerpt<$excerptlen) ) {
                    $title .= sprintf(__("The %s should be longer",seo_content_control_l10domain()), $article_term);
                } elseif ( !($len_content<$articlelen) && $len_excerpt<$excerptlen ) {
                    if ( $editlink ) {
                        $editlink .= "#postexcerpt";
                    }
                    $title .= __("The excerpt should be longer");
                } elseif ( $len_content<$articlelen && $len_excerpt<$excerptlen ) {
                    $title .= sprintf(__("The %s and the excerpt should both be longer",seo_content_control_l10domain()), $article_term);
                }
                if ( $title ) {
                    $title = " title=\"$title\"";
                }

                if ( $link ) {
                    $link = "<a href=\"$link\"$title>$name</a>";
                } else {
                    $link = '"'.$name.'"';
                }

                $edit = $Utils->make_edit_link( $editlink, $title );

                if ( $name && $link ) {
                    $s = "<li>$link";
                    $data = array( $author );
                    if ( $edit ) {
                        $data[] = $edit;
                    }
                    $s .= $Utils->in_parens($data);
                    if ( $showlen ) {
                        $data = array(
                            $article_term.": $len_content ".__("chars", seo_content_control_l10domain())
                        );
                        if ( $excerptlen ) {
                            $data[] = __("excerpt:", seo_content_control_l10domain())." $len_excerpt ".__("chars", seo_content_control_l10domain());
                        }
                        $s .= "<br>".$Utils->in_parens( $data );
                    }
                    $s .= "</li>\n";
                    $d .= $s;
                }
            }
            $d .= "</ol>\n";
        }
        return $d;
    }

}


/**
 *
 *  Pages
 *
 */
class SeoContentControlDescriptionToolPage extends SeoContentControlDescriptionToolPost {

    function SeoContentControlDescriptionToolPage ( &$SeoTool, $Params ) { /* php4 */
        $this->__construct( $SeoTool, $Params );
    }

    function __construct ( &$SeoTool, $Params ) {
        parent::__construct( $SeoTool, $Params, "page", false );
    }

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "pagelen",
            array( "seocc_pagelen",     array(1500, '{I}', 1), __("Page length",seo_content_control_l10domain()),  __("How long should a page be? (default: 1500 chars)",seo_content_control_l10domain()) )
        );
    }

    function getType () {
        return $this->type;
    }

    function fetch_can () {
        return true;
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        $a = $this->get_bad_articles_and_excerpts( $selected_user, $myparams['pagelen'], $myparams['excerptlen'] );
        return $a;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        $a = $this->get_arrays( $selected_user, $myparams );
        $absref = 0;
        return $Utils->summary_row(
            "<a href=\"#pages\">"
                . __("Pages",seo_content_control_l10domain())
                . "</a>"
            ,
            $a, $absref, 10, 40
        );
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $a = $this->get_arrays( $selected_user, $myparams );
        return $this->get_pages($a, $selected_user, $myparams['pagelen'], $myparams['excerptlen']);
    }

    function get_pages ( &$a, $selected_user="", $pagelen=1500 ) {
        $Utils = $this->getUtils();
        $toggle = true;
        $id = "pages";
        $post_type="post";
        $title = __("WordPress Pages", seo_content_control_l10domain());
        $d = "";
        if ( !is_array($a) ) {
            $d .= __("Internal error when retrieving the pages!", seo_content_control_l10domain() );
        } else {
            $d .= __( ""
                . "<p>"
                . "A page in WordPress is quite static in comparison to an article: "
                . "it won't show up in the XML feeds, it is not shown in the list of new "
                . "articles and it has no excerpts. "
                . "WordPress pages are often used to create 'about' or 'contact' pages. "
                . "</p>", seo_content_control_l10domain()
            );
            if ( empty($a['all']) ) {
                $d .= __("You have currently no pages.", seo_content_control_l10domain() );
            } elseif ( !empty($a['no']) || !empty($a['short']) ) {
                $toggle = true;
                $d .= '<table><tr>';

                $d .= '<td style="vertical-align:top;width:50%;">';
                $d .= '<h4 style="margin-top:0;">'.__("Missing content", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['no']) ) {
                    $d .= __("No problem. All pages have some content.", seo_content_control_l10domain());
                } else {
                    $d .= "<p>";
                    $d .= __("These pages have no content at all:", seo_content_control_l10domain())."</p>";
                    $d .= $this->get_article_list( $a['no'], true, $selected_user, $pagelen, 0, __('page',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '<td style="vertical-align:top;">';
                $d .= '<h4 style="margin-top:0;">'.__("Too little content", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['short']) ) {
                    $d .= sprintf(__('No problem. All other pages have at least %s characters of content.', seo_content_control_l10domain()), $pagelen);
                } else {
                    $d .= "<p>";
                    $d .= sprintf(__('The following pages have content with less than %s characters. More unique content would be good:', seo_content_control_l10domain()), $pagelen)."</p>";
                    $d .= $this->get_article_list( $a['short'], true, $selected_user, $pagelen, 0, __('page',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '</tr></table>';
            } else {
                $d .= __("No problem. All pages are long enough.", seo_content_control_l10domain());
            }
        }
        $d = "<p>$d</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

}

/**
 *
 *  Articles
 *
 */
class SeoContentControlDescriptionToolArticle extends SeoContentControlDescriptionToolPost {

    function SeoContentControlDescriptionToolArticle ( &$SeoTool, $Params ) {
        $this->__construct( $SeoTool, $Params );
    }

    function __construct ( &$SeoTool, $Params ) {
        parent::__construct( $SeoTool, $Params, "post", true );
    }

    function getType () {
        return $this->type;
    }

    function fetch_can () {
        return true;
    }

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "articlelen", 
            array( "seocc_articlelen",  array(1500, '{I}', 1), __("Article length",seo_content_control_l10domain()),  __("How long should an article be? (default: 1500 chars)",seo_content_control_l10domain()) )
        );
        $Params->add_param_def(
            "excerptlen",
            array( "seocc_excerptlen",  array(400,  '{I}', 1), __("Excerpt length",seo_content_control_l10domain()),  __("How long should an excerpt be? (default: 400 chars)",seo_content_control_l10domain()) )
        );
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        $a = $this->get_bad_articles_and_excerpts( $selected_user, $myparams['articlelen'], $myparams['excerptlen'] );
        return $a;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        $Utils = $this->getUtils();
        $d = "";
        $a_all = $this->get_arrays( $selected_user, $myparams );
        if ( is_array($a_all) && !empty($a_all) ) {
            $a_content = $a_all['content'];
            $a_excerpt = $a_all['excerpt'];
        } else {
            $a_content = $a_all;
            $a_excerpt = $a_all;
        }
        $absref = 100;
        if ( $Utils->get_current_post_status() != "publish" ) {
            $absref=0;
        }
        $d .= $Utils->summary_row( 
            "<a href=\"#articles\">"
                . __("Posts",seo_content_control_l10domain())
                . "</a>"
            ,
            $a_content, $absref, 10, 40
        );
        $d .= $Utils->summary_row( 
            "<a href=\"#articles\">"
                . __("Excerpts",seo_content_control_l10domain())
                . "</a>"
                . ", "
                . __("for feeds and archives",seo_content_control_l10domain())
            ,
            $a_excerpt, $absref, 10, 40
        );
        return $d;
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $a_all = $this->get_arrays( $selected_user, $myparams );
        $a = $a_all['both'];
        return $this->get_articles_and_excerpts($a, $selected_user, $myparams['articlelen'], $myparams['excerptlen']);
    }

    function get_articles_and_excerpts ( &$a, $selected_user="", $articlelen=1500, $excerptlen=250 ) {
        $Utils = $this->getUtils();
        $toggle = true;
        $id = "articles";
        $post_type="post";
        $title = __("Articles and Article Summaries", seo_content_control_l10domain());
        $d = "";
        if ( !is_array($a) ) {
            $d .= __("Internal error when retrieving the articles!", seo_content_control_l10domain() );
        } else {
            $d .= __( ""
                . "<p>"
                . "When you edit an article in WordPress you find a text area called 'Excerpt' "
                . "right below the edit area of the article. "
                . "Use it to provide a summary or alternate version of the original article. "
                . "Make it not too short! "
                . "</p>", seo_content_control_l10domain()
            );

            if ( empty($a['all']) ) {
                $d .= __("You have currently no articles.", seo_content_control_l10domain() );
            } elseif ( !empty($a['no']) || !empty($a['short']) ) {
                $toggle = true;
                $d .= '<table><tr>';

                $d .= '<td style="vertical-align:top;width:50%;">';
                $d .= '<h4 style="margin-top:0;">'.__("Missing content or missing excerpt", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['no']) ) {
                    $d .= __("No problem. All articles and excerpts have some content.", seo_content_control_l10domain());
                } else {
                    $d .= "<p>";
                    $d .= __("These articles have no content or no excerpt at all:", seo_content_control_l10domain())."</p>";
                    $d .= $this->get_article_list( $a['no'], true, $selected_user, $articlelen, $excerptlen, __('article',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '<td style="vertical-align:top;">';
                $d .= '<h4 style="margin-top:0;">'.__("Too little content or too short excerpt", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['short']) ) {
                    $d .= sprintf(__('No problem. All other articles and excerpts have at least %s characters of content.', seo_content_control_l10domain()), $excerptlen);
                } else {
                    $d .= "<p>";
                    $d .= sprintf(__('The following articles have content with less than %s or excerpts with less than %s characters. More unique content would be good:', seo_content_control_l10domain()), $articlelen, $excerptlen)."</p>";
                    $d .= $this->get_article_list( $a['short'], true, $selected_user, $articlelen, $excerptlen,__('article',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '</tr></table>';
            } else {
                $d .= __("No problem. All articles and excerpts are long enough.", seo_content_control_l10domain());
            }
        }
        $d = "<p>".$d."</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

}

/**
 *
 *  Support for meta descriptions (provided by plugins)
 *
 **/
class SeoContentControlDescriptionToolMetaDescription extends SeoContentControlDescriptionToolPost {

    var $plugin_available = false;
    var $plugin_enabled = false;

    function SeoContentControlDescriptionToolMetaDescription ( &$SeoTool, $Params ) { /* php4 */
        $this->__construct( $SeoTool, $Params );
    }

    function __construct ( &$SeoTool, $Params ) {
        parent::__construct( $SeoTool, $Params, "", false );
    }

    function get_metakeyname () {
        return "";
    }

    function get_edit_id () {
        return "";
    }

    function get_id () {
        return "";
    }

    function get_plugin_name () {
        return "";
    }

    function register_param_defs ( &$Params ) {
        $Params->add_param_def(
            "metadesclen",
            array( "seocc_metadesclen",   array(140,  '{I}', 1), __("Meta length",seo_content_control_l10domain()),      __("How long should the meta description be? (default: 140 chars)",seo_content_control_l10domain()) )
        );
    }

    function get_status_of_metas ( $selected_user="", $post_status="publish" ) {
        // see also: wp-includes/post.php:wp_count_posts()
        $Utils = $this->getUtils();
        global $wpdb;
        $results = null;

        $post_status = $Utils->validate_post_status( $post_status );
        $user = wp_get_current_user();
        $user_id = $user->{'ID'};
        if ( !$user_id ) {
            $user_id = $user->{'user_id'};
        }
        $where_user = "";
        $selected_user = intval($selected_user);
        $metakeyname = $this->get_metakeyname();

        if ( $post_status && $user_id && $metakeyname ) {
            if ( $selected_user && ($selected_user!="*") ) {
                $where_user_and = "post_author='$selected_user' and ";
            }
            $post_status_query = "";
            if ( $post_status && $post_status!="*" ) {
                $post_status_query = "post_status='$post_status' ";
            }
            if ( $user_id ) {
                # select ID, post_author,length(trim(meta_value)) as metalen from wp_posts left join wp_postmeta on wp_posts.ID=wp_postmeta.post_id where post_status="publish" and post_author="1" and wp_postmeta.meta_key='_aioseop_description'; $A = "{$wpdb->posts}";
                $A = "{$wpdb->posts}";
                $B = "{$wpdb->postmeta}";
                $query = ""
                    . "select $A.ID, $A.post_author, $A.post_title, "
                        . "length(trim($B.meta_value)) as metalen "
                    . "from $A "
                    . "left join $B on $A.ID=$B.post_id "
                    . "where "
                        . $where_user_and
                        . $post_status_query
                        . " and $B.meta_key='$metakeyname' "

                    . "union all "

                    . "select $A.ID, $A.post_author, $A.post_title, "
                        . "0 as metalen "
                    . "from $A "
                    . "where "
                        . $where_user_and
                        . $post_status_query

                    . "order by ID DESC "
                ;
                $results = $wpdb->get_results( $wpdb->prepare($query), ARRAY_A );
                // clean dupes from union:
                $results = $this->undupe( $results, "ID", "metalen" );
            }
        }

        return $results;
    }

    function undupe ( &$records, $idName, $lenName ) {
        $unduped = array();
        if ( !empty($records) && is_array($records) ) {
            foreach ( $records as $record ) {
                $key = $record[$idName];
                if ( $key ) {
                    if ( !array_key_exists($key, $unduped) || ($unduped[$key][$lenName]==0) ) {
                        $unduped[$key] = $record;
                    }
                }
            }
        }
        return $unduped;
    }

    function fetch_arrays( $selected_user=0, &$myparams ) { 
        $a = null;
        if ( $this->plugin_available && $this->plugin_enabled ) {
            $a = $this->get_meta_descriptions( $selected_user, $myparams['metadesclen'] );
        }
        return $a;
    }

    function get_details ( $selected_user=0, &$myparams ) {
        $s = "";
        if ( $this->plugin_available && $this->plugin_enabled ) {
            $a = $this->get_arrays( $selected_user, $myparams );
            $s = $this->get_meta_details ( &$a, $selected_user, $myparams['metadesclen'] );
        }
        return $s;
    }

    function get_meta_descriptions ( $selected_user="", $metalen=140 ) {
        $Utils = $this->getUtils();
        $a = false;
        $current_user_id = $Utils->get_current_user_id();
        $users = get_users_of_blog();
        if ( $users && is_array($users) ) {
            $stats = $this->get_status_of_metas( $selected_user, $Utils->get_current_post_status() );
            if ( is_array($stats) ) {
                if ( empty($stats) ) {
                    $a = array();
                } else {
                    $a = array( 'all'=>array(), 'no'=>array(), 'short'=>array() );
                    foreach ( $stats as $stat ) {
                        $id = $stat['ID'];
                        $name = $stat['post_title'];
                        $author = $stat['post_author'];
                        $len = intval($stat['metalen']);
                        $editid = $this->get_edit_id();
                        if ( $editid ) {
                            $editid = "#".$editid;
                        }
                        $record = array( 
                            'id'=>$id, 'author'=>$author, 'name'=>$name, 'len'=>$len,
                            'link'=>get_permalink($id),
                            'editlink'=>get_edit_post_link( $id, "url" ).$editid
                        );
                        $a['all'][] = $record;
                        if ( $id && $name ) {
                            if ( $metalen && !$len ) {
                                $a['no'][] = $record;
                            } elseif ( $metalen && ($len<$metalen) ) {
                                $a['short'][] = $record;
                            }
                        }
                    }
                    $Utils->fill_description_info( $a );
                }
            }
        }
        return $a;
    }

    function get_meta_details ( &$a, $selected_user="", $pagelen=1500 ) {
        $Utils = $this->getUtils();
        $toggle = true;
        $id = $this->get_id();
        $plugin_name = $this->get_plugin_name();

        $post_type="post";
        $title = sprintf(__("Meta Descriptions (Plugin: %s)", seo_content_control_l10domain()), $plugin_name);
        $d = "";
        if ( !is_array($a) ) {
            $d .= __("Internal error when retrieving the meta descriptions!", seo_content_control_l10domain() );
        } else {
            $d .= sprintf(__( ""
                . "<p>"
                . "The plugin <em>%s</em> adds a text area for individual "
                . "meta descriptions for WordPress Posts and WordPress Pages. "
                . "The search engines show these descriptions in their results, "
                . "if they fit well to the search query. You have the most "
                . "control over it, if you adopt it to roughly 140 characters. "
                . "That's the amount of text the search engines actually show. "
                . "</p>", seo_content_control_l10domain()
            ), $plugin_name);
            if ( empty($a['all']) ) {
                $d .= __("You have currently no meta descriptions.", seo_content_control_l10domain() );
            } elseif ( !empty($a['no']) || !empty($a['short']) ) {
                $toggle = true;
                $d .= '<table><tr>';

                $d .= '<td style="vertical-align:top;width:50%;">';
                $d .= '<h4 style="margin-top:0;">'.__("Missing content", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['no']) ) {
                    $d .= __("No problem. All meta descriptions have some content.", seo_content_control_l10domain());
                } else {
                    $d .= "<p>";
                    $d .= __("These meta descriptions are missing:", seo_content_control_l10domain())."</p>";
                    $d .= $this->get_article_list( $a['no'], false, $selected_user, $pagelen, 0, __('meta description',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '<td style="vertical-align:top;">';
                $d .= '<h4 style="margin-top:0;">'.__("Too little content", seo_content_control_l10domain()).'</h4>';
                if ( empty($a['short']) ) {
                    $d .= sprintf(__('No problem. All other meta descriptions are at least %s characters long.', seo_content_control_l10domain()), $pagelen);
                } else {
                    $d .= "<p>";
                    $d .= sprintf(__('The following meta descriptions are shorter than %s characters.', seo_content_control_l10domain()), $pagelen)."</p>";
                    $d .= $this->get_article_list( $a['short'], true, $selected_user, $pagelen, 0, __('meta description',seo_content_control_l10domain()));
                }
                $d .= '</td>';

                $d .= '</tr></table>';
            } else {
                $d .= __("No problem. All meta descriptions are long enough.", seo_content_control_l10domain());
            }
        }
        $d = "<p>$d</p>";
        return $Utils->get_panel( $id, $title, $d, $toggle );
    }

    function _get_summary_row ( $selected_user=0, &$myparams, $plugin_info ) {
        $Utils = $this->getUtils();
        $link = "<a href=\"#".$this->get_id()."\">"
            . __("Meta Descriptions",seo_content_control_l10domain())
            . "</a>"
            . ", "
            . $plugin_info
        ;
        $error = "";
        if ( !$this->plugin_enabled ) {
            $error = __("The plugin is inactive, no meta descriptions available!",seo_content_control_l10domain());
        } elseif ( $this->plugin_bad_description_format ) {
            $error = __("Bad configuration of the Description Format of the plugin: the term '%description%' is missing!",seo_content_control_l10domain());
        }
        if ( $error ) {
            return $Utils->summary_status_row( $link, $error );
        }
        $a = $this->get_arrays( $selected_user, $myparams );
        return $Utils->summary_row( 
            $link, $a, $a['all_info'], 1, 20
        );
        return "";
    }


}

/**
 *
 *  Support for descriptions of All-In-One-SEO-pack 
 *
 **/
class SeoContentControlDescriptionToolAioseop extends SeoContentControlDescriptionToolMetaDescription {

    var $plugin_bad_description_format = false;
    var $plugin_old_style = false;

    function get_id () {
        return "allinoneseometa";
    }

    function get_metakeyname () {
        if ( $this->plugin_old_style ) {
            return "description";
        } else {
            return "_aioseop_description";
        }
    }

    function get_edit_id () {
        return "aiosp";
    }

    function get_plugin_name () {
        return "All in One SEO Pack";
    }

    function fetch_can () {
        $Utils = $this->getUtils();
        $can = false;
        $path = $Utils->find_plugin_path( $this->get_plugin_name(), "all-in-one-seo-pack/all_in_one_seo_pack.php" );
        if ( $path ) {
            $this->plugin_available = true;
            $can = true;
            if ( is_plugin_active($path) ) {
                $options = get_option('aioseop_options');
                if ( empty($options) ) {
                    // old version of All In One SEO?
                    // Read out the old stuff and emulate the new structure:
                    $options = array();
                    $options['aiosp_can'] = get_option('aiosp_can');
                    if ( $options['aiosp_can'] && ($options['aiosp_can']=="on") ) {
                        $options['aiosp_enabled'] = "1";
                        $this->plugin_old_style = true;
                    }
                    $options['aiosp_description_format'] = get_option('aiosp_description_format');
                }
                if ( !empty($options) && is_array($options) && $options{'aiosp_enabled'} ) {
                    $this->plugin_enabled = true;
                    $description_format = $options{'aiosp_description_format'};
                    if ( !preg_match('/%description%/', $description_format) ) {
                        $this->plugin_bad_description_format = true;
                    }
                } else {
                    $this->plugin_enabled = false;
                }
            }
        }
        return $can;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        return $this->_get_summary_row( 
            $selected_user, $myparams, 
            __("Plugin: All In One SEO",seo_content_control_l10domain())
        );
    }

}

/**
 *
 *  Support for descriptions of wpSeo
 *
 **/
class SeoContentControlDescriptionToolWpseo extends SeoContentControlDescriptionToolMetaDescription {

    function get_id () {
        return "wpseometa";
    }

    function get_metakeyname () {
        return "_wpseo_edit_description";
    }

    function get_edit_id () {
        return "wpseo_edit";
    }

    function get_plugin_name () {
        return "wpSEO";
    }

    function fetch_can () {
        $Utils = $this->getUtils();
        $can = false;
        $path = $Utils->find_plugin_path( $this->get_plugin_name(), "wpseo/wpseo.php" );
        if ( $path ) {
            $this->plugin_available = true;
            $can = true;
            if ( is_plugin_active($path) ) {
                $options = get_option('wpseo_options');
                if ( !empty($options) && is_array($options) ) {
                    if ( $options{'wp_seo_desc_enable'} ) {
                        $this->plugin_enabled = true;
                    } else {
                        $this->plugin_enabled = false;
                    }
                }
            }
        }
        return $can;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        return $this->_get_summary_row( 
            $selected_user, $myparams, 
            __("Plugin: wpSeo",seo_content_control_l10domain())
        );
    }

}

/**
 *
 *  Support for descriptions of PlatinumSEO
 *
 *  Note: PlatinumSEO is reusing some field names of All In One SEO.
 *
 **/
class SeoContentControlDescriptionToolPlatinumseo extends SeoContentControlDescriptionToolMetaDescription {

    function get_id () {
        return "wpplatinumseo";
    }

    function get_metakeyname () {
        return "description";
    }

    function get_edit_id () {
        return "psp_description";
    }

    function get_plugin_name () {
        return "Platinum SEO Pack";
    }

    function fetch_can () {
        $Utils = $this->getUtils();
        $can = false;
        $path = $Utils->find_plugin_path( $this->get_plugin_name(), "platinum-seo-pack/platinum_seo_pack.php" );
        if ( $path ) {
            $this->plugin_available = true;
            $can = true;
            if ( is_plugin_active($path) ) {
                $this->plugin_enabled = true;
                $description_format = get_option('aiosp_description_format');
                if ( !$description_format || !preg_match('/%description%/', $description_format) ) {
                    $this->plugin_bad_description_format = true;
                }
            }
        }
        return $can;
    }

    function get_summary_row ( $selected_user=0, &$myparams ) {
        return $this->_get_summary_row( 
            $selected_user, $myparams, 
            __("Plugin: Platinum SEO",seo_content_control_l10domain())
        );
    }

}

/*
 * Plug the controller into the WordPress hooks:
 */

/*
 *
 *  Localization
 *
 */
function seo_content_control_localize() {
    if (function_exists('load_plugin_textdomain')) {
        if ( !defined('WP_PLUGIN_DIR') ) {
            load_plugin_textdomain(seo_content_control_l10domain(), str_replace( ABSPATH, '', dirname(__FILE__)));
        } else {
            load_plugin_textdomain(seo_content_control_l10domain(), false, basename(dirname(__FILE__)));
        }
    }
}
add_action( 'init', 'seo_content_control_localize' );


/*
 *
 *  Tool Panel:
 *
 */

global $SeoContentControlDescriptionTool;

function seo_content_control_simple_path_join ( $base="", $path="" ) {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function seo_content_control_get_description_tool() {
    global $SeoContentControlDescriptionTool;
    if ( !$SeoContentControlDescriptionTool ) {
        $SeoContentControlDescriptionTool = new SeoContentControlDescriptionTool();
    }
    return $SeoContentControlDescriptionTool;
}

function seo_content_control_add_menu() {
    $Tool = seo_content_control_get_description_tool();
    if ( $Tool ) {
        $Tool->add_menu();
    }
}

function seo_content_control_descriptiontool_print_scripts() {
    wp_enqueue_script( 
        "seocc-descriptions", 
        seo_content_control_simple_path_join (
            WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/js/descriptions.js"
        ),
        array( 'jquery' )
    );
}

function seo_content_control_descriptiontool_print_styles() {
    $myStyleUrl = seo_content_control_simple_path_join ( 
        WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/css/descriptions.css"
    );
    $myStyleFile = seo_content_control_simple_path_join ( 
        WP_PLUGIN_DIR, basename( dirname( __FILE__ ) )."/css/descriptions.css"
    );
    if ( file_exists($myStyleFile) ) {
        wp_register_style('seocc-descriptions', $myStyleUrl);
        wp_enqueue_style( 'seocc-descriptions');
    }
}

function seo_content_control_descriptiontool_show_menu () {
    $Tool = seo_content_control_get_description_tool();
    if ( $Tool ) {
        $Tool->show_menu();
    }
}

add_action( 'admin_menu', 'seo_content_control_add_menu' );


/*
 * With WP 3.1 the postexcerpt field is hidden in the editor by default.
 * We definitely need to show it...
 */
function seo_content_control_default_hidden_meta_boxes( $hidden, &$screen ) {
    if ( $hidden && is_array($hidden) ) {
        $key = array_search('postexcerpt', $hidden);
        if ( $key !== FALSE ) {
            unset( $hidden[$key] );
        }
    }
    return $hidden;
}

add_filter( 'default_hidden_meta_boxes', 'seo_content_control_default_hidden_meta_boxes', 10, 2 );

/*
 * The WP postexcerpt field is so very tiny, enlarge it (using .js):
 */
add_action("admin_print_scripts-post.php", 'seo_content_control_edit_post_scripts');
function seo_content_control_edit_post_scripts() {
    wp_enqueue_script( 
        "seocc-editposts", 
        seo_content_control_simple_path_join (
            WP_PLUGIN_URL, basename( dirname( __FILE__ ) )."/js/editposts.js"
        ),
        array( 'jquery' )
    );
}


/*
 * _PHP4_COMPAT_ 
 *
 * Simple steps to remain compatible with php4:
 *
 * 1. 'var $foo = "bar"' instead of 'private $foo = "bar"'
 * 2. No use of "static"
 * 3. php4-Constructor (name of the class) calling php5 constructor, e.g. for class Foo: function Foo() { $this->__construct(); }
 *
 */

?>
