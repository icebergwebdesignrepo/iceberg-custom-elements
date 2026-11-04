<?php
namespace IcebergCustomElements;

// Restore the Gravity Forms submit button classes used by Breakdance button styles.
add_filter(
    'gform_submit_button',
    __NAMESPACE__ . '\\add_custom_css_classes',
    10,
    2
);

/** Add Breakdance button classes without replacing Gravity Forms' own classes. */
function add_custom_css_classes( $button, $form ) {
    // The HTML Processor was introduced after our declared minimum WP version.
    if ( ! class_exists( \WP_HTML_Processor::class ) ) {
        return $button;
    }

    $fragment = \WP_HTML_Processor::create_fragment( $button );
    if ( ! $fragment || ! $fragment->next_token() ) {
        return $button;
    }

    $fragment->add_class( 'button-atom' );
    $fragment->add_class( 'button-atom--primary' );
    $fragment->add_class( 'bde-button__button' );

    return $fragment->get_updated_html();
}
