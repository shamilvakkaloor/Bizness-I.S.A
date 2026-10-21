<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
class AccessTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();$this->seed();}
 public function test_staff_cannot_list_users():void {
  $staff=User::create(['name'=>'Staff','email'=>'staff@example.test','password'=>'test-password-123','role'=>'staff','permissions'=>['view'],'branch_ids'=>[1]]);
  $this->actingAs($staff)->getJson('/api/users')->assertForbidden();
 }
 public function test_admin_can_manage_staff_without_exposing_passwords():void {
  $admin=User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'test-password-123','role'=>'admin']);
  $payload=['name'=>'Staff','email'=>'staff@example.test','password'=>'test-password-123','permissions'=>['view'],'branch_ids'=>[1],'discount_limit_bps'=>0,'active'=>true];
  $id=$this->actingAs($admin)->postJson('/api/users',$payload)->assertOk()->json('id');
  $this->getJson('/api/users')->assertOk()->assertJsonMissingPath('0.password');
  $this->postJson('/api/users',$payload)->assertUnprocessable()->assertJsonValidationErrors('email');
  unset($payload['password']);$payload['active']=false;
  $this->putJson('/api/users/'.$id,$payload)->assertOk();
  $this->assertFalse(User::find($id)->active);
 }
 public function test_guest_login_screen_and_protected_workspace():void {
  $this->get('/login')->assertOk()->assertSee('Sign in to workspace');
  $this->get('/')->assertRedirect('/login');
  $this->getJson('/api/users')->assertUnauthorized();
 }
 public function test_disabled_account_cannot_login():void {
  User::create(['name'=>'Disabled','email'=>'off@example.test','password'=>'test-password-123','active'=>false]);
  $this->post('/login',['email'=>'off@example.test','password'=>'test-password-123'])->assertSessionHasErrors();
  $this->assertGuest();
 }
}
