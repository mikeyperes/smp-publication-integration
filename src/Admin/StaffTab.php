<?php

namespace smp_publication_integration\Admin;

use Hexa\PluginCore\WpAdminAjax\AjaxGuard;
use smp_publication_integration\Admin\People\PeopleEditor;
use smp_publication_integration\Content\Staff;
use smp_publication_integration\Content\People\PeopleCards;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Staff writer membership and presentation, using the shared people editor. */
final class StaffTab {
    public const TAB = 'staff';

    public function register(): void {
        add_filter( 'smpi_render_dashboard_tab', [ $this, 'maybe_render' ], 10, 2 );
        foreach ( [ 'save_people', 'style', 'apply_template', 'create_page' ] as $action ) {
            add_action( 'wp_ajax_smpi_staff_' . $action, [ $this, 'ajax_' . $action ] );
        }
    }

    public function maybe_render( $rendered, string $tab ) {
        if ( self::TAB !== $tab ) {
            return $rendered;
        }
        $this->render();
        return true;
    }

    private function guard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You are not allowed to change these settings.' ], 403 );
        }
        AjaxGuard::require_nonce_or_error( Ajax::NONCE, 'nonce' );
    }

    public function ajax_save_people(): void {
        $this->guard();
        $people = json_decode( (string) wp_unslash( $_POST['people'] ?? '[]' ), true );
        $order = [];
        foreach ( is_array( $people ) ? $people : [] as $person ) {
            $id = absint( is_array( $person ) ? ( $person['id'] ?? 0 ) : 0 );
            if ( $id && get_userdata( $id ) ) {
                Staff::set_member( $id, true, (string) ( $person['title'] ?? '' ) );
                $order[] = $id;
            }
        }
        foreach ( Staff::member_ids() as $id ) {
            if ( ! in_array( $id, $order, true ) ) {
                Staff::set_member( $id, false );
            }
        }
        Staff::update_settings( [ 'order' => array_values( array_unique( $order ) ) ] );
        do_action( 'litespeed_purge_all' );
        $count = count( Staff::member_ids() );
        wp_send_json_success( [ 'message' => $count . ( 1 === $count ? ' person is' : ' people are' ) . ' on the staff list.', 'example' => $this->example_html() ] );
    }

    public function ajax_style(): void {
        $this->guard();
        $style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
        if ( ! isset( PeopleCards::STYLES[ $style ] ) ) {
            wp_send_json_error( [ 'message' => 'Unknown style.' ] );
        }
        Staff::update_settings( [ 'style' => $style ] );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'Style saved.', 'example' => $this->example_html() ] );
    }

    public function ajax_apply_template(): void {
        $this->guard();
        $page_id = PeopleCards::find_page( Staff::PAGE_TYPE, Staff::PATHS );
        if ( ! $page_id ) {
            wp_send_json_error( [ 'message' => 'There is no Staff page to update.' ] );
        }
        PeopleCards::apply_template( $page_id, Staff::default_template() );
        do_action( 'litespeed_purge_all' );
        wp_send_json_success( [ 'message' => 'The page now uses the default template. Its previous content is saved as a revision.' ] );
    }

    public function ajax_create_page(): void {
        $this->guard();
        if ( PeopleCards::find_page( Staff::PAGE_TYPE, Staff::PATHS ) ) {
            wp_send_json_success( [ 'message' => 'The Staff page already exists.' ] );
        }
        $page = PeopleCards::create_page( Staff::PAGE_TYPE, 'Meet The Staff', 'meet-the-staff', Staff::default_template() );
        if ( ! empty( $page['error'] ) ) {
            wp_send_json_error( [ 'message' => 'The page could not be created: ' . $page['error'] ] );
        }
        wp_send_json_success( [ 'message' => 'The Staff page was created with the default template.' ] );
    }

    private function example_html(): string {
        $html = Staff::render();
        return '' !== $html ? $html : '<p class="description">No one is on the list yet.</p>';
    }

    private function render(): void {
        $settings = Staff::settings();
        $page_id  = PeopleCards::find_page( Staff::PAGE_TYPE, Staff::PATHS );
        PeopleEditor::render(
            [
                'id'               => 'smpi-staff-tab',
                'ajax_prefix'      => 'smpi_staff_',
                'top_html'         => '<section><h3>Staff</h3><p class="lead">Everyone with Staff writer switched on in their user profile. Adding or removing a person here updates that same switch.</p></section>',
                'show_body'        => true,
                'page'             => [ 'id' => $page_id, 'has_list' => $page_id && PeopleCards::page_has_shortcode( $page_id, Staff::SHORTCODE ) ],
                'page_label'       => 'Staff',
                'list_label'       => 'Staff',
                'people_intro'     => 'Drag to set the order of appearance. Adding someone turns on Staff writer on their profile; removing them turns it off. Profile changes show here too. Staff Title is optional and shown only on this list.',
                'empty'            => 'No one yet. Search above to add the first person.',
                'members'          => Staff::member_ids(),
                'row'              => static fn( int $id ): array => [ 'title' => Staff::title( $id ) ],
                'title_field'      => true,
                'title_label'      => 'Staff Title',
                'shortcode'        => Staff::SHORTCODE,
                'block'            => 'smpi-staff',
                'item'             => 'smpi-staff-member',
                'style'            => $settings['style'],
                'example_html'     => $this->example_html(),
                'attributes'       => [
                    'with_photo'    => [ 'default' => 'the site\'s "hide authors without a featured image" setting', 'description' => '1 leaves out people without their own profile photo; 0 shows everyone.', 'options' => [ '1', '0' ] ],
                    'with_articles' => [ 'default' => 'the site\'s "hide authors without articles" setting', 'description' => '1 leaves out people who have not published anything yet; 0 shows everyone.', 'options' => [ '1', '0' ] ],
                ],
            ]
        );
    }
}
