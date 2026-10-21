<?php
namespace Tests\Unit;
use App\Support\Amount;
use Tests\TestCase;
class AmountTest extends TestCase {
 public function test_money_is_parsed_without_binary_float_rounding():void {$this->assertSame(1001,Amount::parse('10.01'));$this->assertSame(-1,Amount::parse('-0.01'));$this->assertSame(1250,Amount::parse('1.250',3));$this->assertSame(3,Amount::divide(5,2));}
 public function test_excess_precision_is_rejected():void {$this->expectException(\Illuminate\Validation\ValidationException::class);Amount::parse('1.001');}
}

