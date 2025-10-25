<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Range extends Filter_Element {
	public $name         = 'filter-range';
	public $icon         = 'ti-arrows-horizontal';
	public $filter_type  = 'range';
	private $min_value   = null;
	private $max_value   = null;
	private $current_min = null;
	private $current_max = null;

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Range', 'bricks' );
	}
	public function set_control_groups() {
		$this->control_groups['label'] = [
			'title' => esc_html__( 'Label', 'bricks' ),
		];

		$this->control_groups['input'] = [
			'title'    => esc_html__( 'Input', 'bricks' ),
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->control_groups['slider'] = [
			'title'    => esc_html__( 'Slider', 'bricks' ),
			'required' => [ 'displayMode', '!=', 'input' ],
		];
	}

	public function set_controls() {
		// SORT / FILTER
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			// Support customField only
			unset( $filter_controls['filterSource']['options']['taxonomy'] );
			unset( $filter_controls['filterSource']['options']['wpField'] );
			unset( $filter_controls['filterTaxonomy'] );
			unset( $filter_controls['filterHierarchical'] );
			unset( $filter_controls['filterTaxonomyHideEmpty'] );
			unset( $filter_controls['filterHideCount'] );
			unset( $filter_controls['filterHideEmpty'] );
			unset( $filter_controls['labelMapping'] );
			unset( $filter_controls['customLabelMapping'] );
			unset( $filter_controls['fieldCompareOperator'] );

			$this->controls = array_merge( $this->controls, $filter_controls );
		}

		// MODE
		$this->controls['modeSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Mode', 'bricks' ),
			'desc'  => esc_html__( 'Min/max values are set automatically based on query loop results.', 'bricks' ),
		];

		$this->controls['displayMode'] = [
			'label'       => esc_html__( 'Mode', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'range' => esc_html__( 'Slider', 'bricks' ),
				'input' => esc_html__( 'Input', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Slider', 'bricks' ),
		];

		$this->controls['step'] = [
			'label'    => esc_html__( 'Step', 'bricks' ),
			'type'     => 'number',
			'required' => [ 'displayMode', '=', 'input' ], // NOTE: Why limit step to input mode only?
		];

		// LABEL
		$this->controls['labelMin'] = [
			'group'  => 'label',
			'label'  => esc_html__( 'Min', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['labelMax'] = [
			'group'  => 'label',
			'label'  => esc_html__( 'Max', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['labelDirection'] = [
			'group'   => 'label',
			'label'   => esc_html__( 'Direction', 'bricks' ),
			'inline'  => true,
			'tooltip' => [
				'content'  => 'flex-direction',
				'position' => 'top-left',
			],
			'type'    => 'direction',
			'css'     => [
				[
					'property' => 'flex-direction',
					'selector' => '.min-max-wrap > *, .value-wrap > *',
				],
			],
		];

		$this->controls['labelGap'] = [
			'group' => 'label',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.value-wrap > span',
				],
				[
					'property' => 'gap',
					'selector' => '.min-max-wrap > div',
				],
			],
		];

		$this->controls['labelTypography'] = [
			'group' => 'label',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.label',
				],
			],
		];

		// Auto-set via JS: toLocaleString()
		$this->controls['labelThousandSeparator'] = [
			'group'    => 'label',
			'label'    => esc_html__( 'Thousand separator', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'displayMode','!=','input' ],
		];

		$this->controls['labelSeparatorText'] = [
			'group'       => 'label',
			'label'       => esc_html__( 'Separator', 'bricks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => ',',
			'required'    => [
				[ 'displayMode', '!=', 'input' ],
				[ 'labelThousandSeparator', '=', true ],
			],
		];

		// INPUT
		$this->controls['placeholderMin'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Placeholder', 'bricks' ) . ' (' . esc_html__( 'Min', 'bricks' ) . ')',
			'type'     => 'text',
			'inline'   => true,
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['placeholderMax'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Placeholder', 'bricks' ) . ' (' . esc_html__( 'Max', 'bricks' ) . ')',
			'type'     => 'text',
			'inline'   => true,
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputBackgroundColor'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputBorder'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Border', 'bricks' ),
			'type'     => 'border',
			'css'      => [
				[
					'property' => 'border',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputTypography'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputWidth'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Width', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'width',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		// SLIDER (@since 1.11)
		$this->controls['sliderSpacing'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Spacing', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'padding-top',
					'selector' => '.double-slider-wrap',
				],
				[
					'property' => 'margin-top',
					'selector' => '.double-slider-wrap .value-wrap',
				],
			],
			'placeholder' => '14px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderBarHeight'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Bar Height', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap .slider-wrap .slider-base',
				],
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap .slider-wrap .slider-track',
				],
			],
			'placeholder' => '2px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderBarColor'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Bar color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap .slider-wrap .slider-base',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderBarColorActive'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Bar color', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap .slider-wrap .slider-track',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderThumbColor'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Thumb color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderThumbSize'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Thumb size', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'width',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'width',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'height',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'height',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'border-radius',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-radius',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'placeholder' => '14px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderThumbBorder'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Thumb border width', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'placeholder' => '2px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];
	}

	private function set_as_filter() {
		$settings = $this->settings;

		// Check required filter settings
		if ( empty( $settings['filterQueryId'] ) || empty( $settings['filterSource'] ) ) {
			return;
		}

		$this->prepare_sources();

		/**
		 * Get min/max value from $this->choices_source
		 */
		if ( ! empty( $this->choices_source ) ) {
			foreach ( $this->choices_source as $source ) {
				$choice_value = $source['filter_value'] ?? false;

				if ( ! $choice_value ) {
					continue;
				}

				// Force to convert to float
				$choice_value = (float) $choice_value;

				// Set min/max value, set as Integer, we only support Integer
				if ( $this->min_value === null || $choice_value < $this->min_value ) {
					// If the value is 1.9, it will be converted to 1
					$choice_value = floor( $choice_value );
					// Convert to integer - Set min value
					$this->min_value = (int) $choice_value;
				}

				if ( $this->max_value === null || $choice_value > $this->max_value ) {
					// If the value is 1.9, it will be converted to 2
					$choice_value = ceil( $choice_value );
					// Convert to integer - Set max value
					$this->max_value = (int) $choice_value;
				}
			}
		}

		// Insert filter settings as data-brx-filter attribute
		$filter_settings                 = $this->get_common_filter_settings();
		$filter_settings['filterSource'] = $settings['filterSource'];

		// min, max, step values
		$filter_settings['min']  = $this->min_value ?? 0;
		$filter_settings['max']  = $this->max_value ?? 100;
		$filter_settings['step'] = $settings['step'] ?? 1;

		// thousand separator
		$display_mode = $settings['displayMode'] ?? 'range';
		if ( $display_mode === 'range' ) {
			$filter_settings['thousands'] = ! empty( $settings['labelThousandSeparator'] ) ? $settings['labelThousandSeparator'] : '';
			$filter_settings['separator'] = ! empty( $settings['labelSeparatorText'] ) ? $this->render_dynamic_data( $settings['labelSeparatorText'] ) : '';
		}

		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings = $this->settings;

		if ( $this->is_filter_input() ) {
			$this->set_as_filter();

			// Return: Indexing in progress (@since 1.10)
			if ( $this->is_indexing() ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'Indexing in progress.', 'bricks' ),
					]
				);
			}
		}

		// Return: No filter source selected
		if ( empty( $settings['filterSource'] ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No filter source selected.', 'bricks' ),
				]
			);
		}

		$this->min_value = $this->min_value ?? 0;
		$this->max_value = $this->max_value ?? 100;

		// Avoid division by zero (@since 1.11)
		if ( $this->min_value === $this->max_value ) {
			$this->max_value += 1;
		}

		$this->current_min = $this->min_value ?? 0;
		$this->current_max = $this->max_value ?? 100;

		// In filter AJAX call, filterValue is the current filter value
		if ( isset( $settings['filterValue'] ) && is_array( $settings['filterValue'] ) ) {
			// The expected value is an array, first element is min, second element is max
			$current_value = $settings['filterValue'];

			if ( isset( $current_value[0] ) && ! empty( $current_value[0] ) ) {
				$this->current_min = $current_value[0];
			}

			if ( isset( $current_value[1] ) && ! empty( $current_value[1] ) ) {
				$this->current_max = $current_value[1];
			}
		}

		echo "<div {$this->render_attributes('_root')}>";

		// Range slider UI
		$this->maybe_render_range_slider();

		// Input fields UI - must be rendered
		$this->render_input_fields();

		echo '</div>'; // end root
	}

	private function maybe_render_range_slider() {
		$settings     = $this->settings;
		$display_mode = $settings['displayMode'] ?? 'range';
		$label_min    = ! empty( $settings['labelMin'] ) ? $this->render_dynamic_data( $settings['labelMin'] ) : '';
		$label_max    = ! empty( $settings['labelMax'] ) ? $this->render_dynamic_data( $settings['labelMax'] ) : '';
		$thousands    = ! empty( $settings['labelThousandSeparator'] ) ? $settings['labelThousandSeparator'] : '';
		$separator    = ! empty( $settings['labelSeparatorText'] ) ? $this->render_dynamic_data( $settings['labelSeparatorText'] ) : ',';

		if ( $display_mode !== 'range' ) {
			return;
		}

		// Adjust slider-wrap width and left position (@since 1.11)
		$min_value = $this->current_min ?? 0;
		$max_value = $this->current_max ?? 100;

		$min_percent = ( $min_value - $this->min_value ) / ( $this->max_value - $this->min_value ) * 100;
		$max_percent = ( $max_value - $this->min_value ) / ( $this->max_value - $this->min_value ) * 100;
		$width       = $max_percent - $min_percent;

		// Tweak for firefox, otherwise there might be a small line visible
		if ( $width === 100 ) {
			$width = 99;
		}

		// @since 1.11.1: If it's RTL, we need to offset from left

		$style = 'width:' . $width . '%; ' . ( is_rtl() ? 'right:' : 'left:' ) . $min_percent . '%;';

		// Hide the track if the width is less than 2%. Otherwise, there might be a small line visible
		if ( $width <= 2 ) {
			$style .= ' visibility: hidden;';
		}

		echo '<div class="double-slider-wrap">';

		// Slider wrap, slider-base, slider-track (@since 1.11)
		echo '<div class="slider-wrap">';
		echo '<div class="slider-base"></div>';
		echo '<div class="slider-track" style="' . $style . '"></div>';

		$this->set_attribute( 'min-range', 'type', 'range' );
		$this->set_attribute( 'min-range', 'class', 'min' );
		$this->set_attribute( 'min-range', 'name', "form-field-min-{$this->id}" );
		$this->set_attribute( 'min-range', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'min-range', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'min-range', 'value', $this->current_min );
		$this->set_attribute( 'min-range', 'tabindex', '0' ); // Safari needs this or focusin event won't fire (@since 1.11)

		echo "<input {$this->render_attributes( 'min-range' )}>";

		$this->set_attribute( 'max-range', 'type', 'range' );
		$this->set_attribute( 'max-range', 'class', 'max' );
		$this->set_attribute( 'max-range', 'name', "form-field-max-{$this->id}" );
		$this->set_attribute( 'max-range', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'max-range', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'max-range', 'value', $this->current_max );
		$this->set_attribute( 'max-range', 'tabindex', '0' ); // Safari needs this or focusin event won't fire (@since 1.11)

		echo "<input {$this->render_attributes( 'max-range' )}>";

		echo '</div>';

		// Hardcode HTML
		echo '<div class="value-wrap">';

		$min_value = $this->current_min;
		$max_value = $this->current_max;

		if ( ! empty( $thousands ) ) {
			$min_value = number_format( $min_value, 0, '.', $separator );
			$max_value = number_format( $max_value, 0, '.', $separator );
		}

		$value_wrapper_html  = '<span class="lower">';
		$value_wrapper_html .= ! empty( $label_min ) ? '<span class="label">' . $label_min . '</span>' : '';
		$value_wrapper_html .= '<span class="value">' . $min_value . '</span>';
		$value_wrapper_html .= '</span>';

		$value_wrapper_html .= '<span class="upper">';
		$value_wrapper_html .= ! empty( $label_max ) ? '<span class="label">' . $label_max . '</span>' : '';
		$value_wrapper_html .= '<span class="value">' . $max_value . '</span>';
		$value_wrapper_html .= '</span>';

		echo $value_wrapper_html;

		echo '</div>';

		echo '</div>';
	}

	private function render_input_fields() {
		$settings        = $this->settings;
		$display_mode    = $settings['displayMode'] ?? 'range';
		$label_min       = ! empty( $settings['labelMin'] ) ? $this->render_dynamic_data( $settings['labelMin'] ) : '';
		$label_max       = ! empty( $settings['labelMax'] ) ? $this->render_dynamic_data( $settings['labelMax'] ) : '';
		$placeholder_min = ! empty( $settings['placeholderMin'] ) ? $this->render_dynamic_data( $settings['placeholderMin'] ) : esc_html__( 'Min', 'bricks' );
		$placeholder_max = ! empty( $settings['placeholderMax'] ) ? $this->render_dynamic_data( $settings['placeholderMax'] ) : esc_html__( 'Max', 'bricks' );

		$this->set_attribute( 'min-max-wrap', 'class', 'min-max-wrap' );

		if ( $display_mode === 'range' ) {
			// Hide input fields if range slider is used
			$this->set_attribute( 'min-max-wrap', 'style', 'display: none;' );
		}

		echo "<div {$this->render_attributes( 'min-max-wrap' )}>";

		// Min. value
		echo '<div class="min-wrap">';

		if ( ! empty( $label_min ) ) {
			echo '<span class="label">' . $label_min . '</span>';
		}

		$this->set_attribute( 'min-input', 'type', 'number' );
		$this->set_attribute( 'min-input', 'class', 'min' );
		$this->set_attribute( 'min-input', 'name', "form-field-min-{$this->id}" );
		$this->set_attribute( 'min-input', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'min-input', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'min-input', 'step', $settings['step'] ?? 1 );
		$this->set_attribute( 'min-input', 'placeholder', $placeholder_min );
		$this->set_attribute( 'min-input', 'value', $this->current_min );
		echo "<input {$this->render_attributes( 'min-input' )}>";

		echo '</div>';

		// Max. value
		echo '<div class="max-wrap">';

		if ( ! empty( $label_max ) ) {
			echo '<span class="label">' . $label_max . '</span>';
		}

		$this->set_attribute( 'max-input', 'type', 'number' );
		$this->set_attribute( 'max-input', 'class', 'max' );
		$this->set_attribute( 'max-input', 'name', "form-field-max-{$this->id}" );
		$this->set_attribute( 'max-input', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'max-input', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'max-input', 'step', $settings['step'] ?? 1 );
		$this->set_attribute( 'max-input', 'placeholder', $placeholder_max );
		$this->set_attribute( 'max-input', 'value', $this->current_max );
		echo "<input {$this->render_attributes( 'max-input' )}>";

		echo '</div>';

		echo '</div>';
	}
}
