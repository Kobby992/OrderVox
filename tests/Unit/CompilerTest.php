<?php
/**
 * Tests for the template Compiler.
 *
 * @package Ordervox
 */

namespace Webbcode\Ordervox\Tests\Unit;

use Brain\Monkey\Functions;
use Webbcode\Ordervox\Templates\Compiler;

/**
 * Covers placeholder compilation, Meta rule checks, previews and value cleaning.
 */
final class CompilerTest extends TestCase {

	private const KNOWN = array( 'order_number', 'order_total', 'customer_full_name', 'order_admin_url' );

	protected function set_up(): void {
		parent::set_up();
		Functions\when( '__' )->returnArg();
	}

	public function test_compile_numbers_known_placeholders_in_order(): void {
		$result = Compiler::compile( "Order #{order_number} for {order_total}.\r\nFrom {customer_full_name}, see {unknown}.", self::KNOWN );

		$this->assertSame( "Order #{{1}} for {{2}}.\nFrom {{3}}, see {unknown}.", $result['text'] );
		$this->assertSame( array( 'order_number', 'order_total', 'customer_full_name' ), $result['keys'] );
	}

	public function test_repeated_placeholder_gets_its_own_number(): void {
		$result = Compiler::compile( 'A {order_number} and B {order_number} end.', self::KNOWN );

		$this->assertSame( 'A {{1}} and B {{2}} end.', $result['text'] );
		$this->assertSame( array( 'order_number', 'order_number' ), $result['keys'] );
	}

	public function test_valid_template_has_no_errors(): void {
		$result = Compiler::validate( 'New order #{order_number} for {order_total} from {customer_full_name}. Open {order_admin_url} to view it.', 'Sent by Ordervox', self::KNOWN );

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	/**
	 * @dataProvider invalid_templates
	 */
	public function test_meta_rules( string $body, string $footer, string $expected ): void {
		$errors = Compiler::validate( $body, $footer, self::KNOWN )['errors'];
		$this->assertNotEmpty( $errors );
		$this->assertStringContainsString( $expected, implode( ' ', $errors ) );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function invalid_templates(): array {
		return array(
			'empty'                 => array( '   ', '', 'cannot be empty' ),
			'starts with variable'  => array( '{order_number} is new, have a look.', '', 'start or end' ),
			'ends with variable'    => array( 'New order with number {order_number}', '', 'start or end' ),
			'adjacent variables'    => array( 'Order {order_number} {order_total} placed today.', '', 'next to each other' ),
			'too long'              => array( 'Order ' . str_repeat( 'x', 1100 ) . ' done.', '', 'too long' ),
			'placeholder in footer' => array( 'Order {order_number} placed.', 'By {order_total}', 'footer cannot contain' ),
			'footer too long'       => array( 'Order {order_number} placed.', str_repeat( 'f', 61 ), 'footer is too long' ),
		);
	}

	public function test_warns_about_unknown_placeholders_and_dense_templates(): void {
		$warnings = Compiler::validate( 'Hi {nope} x {order_number}, {order_total}, {customer_full_name} ok.', '', self::KNOWN )['warnings'];

		$this->assertCount( 2, $warnings );
		$this->assertStringContainsString( '{nope}', $warnings[0] );
		$this->assertStringContainsString( 'many placeholders', $warnings[1] );
	}

	public function test_render_fills_values_and_keeps_unknown(): void {
		$text = Compiler::render(
			"Order #{order_number}\nTotal {order_total} {other}",
			array(
				'order_number' => '1001',
				'order_total'  => '',
			)
		);
		$this->assertSame( "Order #1001\nTotal - {other}", $text );
	}

	public function test_clean_value(): void {
		$this->assertSame( '12 Main St · Springfield', Compiler::clean_value( "12 Main St\r\n  Springfield" ) );
		$this->assertSame( 'a b c', Compiler::clean_value( "a\t\tb     c" ) );
		$this->assertSame( '-', Compiler::clean_value( '   ' ) );

		$long = Compiler::clean_value( str_repeat( 'é', 400 ) );
		$this->assertSame( Compiler::VALUE_MAX, mb_strlen( $long ) );
		$this->assertStringEndsWith( '…', $long );
	}
}
