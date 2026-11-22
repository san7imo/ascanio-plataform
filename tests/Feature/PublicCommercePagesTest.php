<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCommercePagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'contact' => ['/contacto', 'Contact', 'contact.heading', '¿Necesitas ayuda?'],
            'shop' => ['/shop', 'Shop', 'products.0.key', 'vevo'],
            'vevo' => ['/product/distribucion-vevo', 'Product', 'product.key', 'vevo'],
            'sync' => ['/product/sincronizacion', 'Product', 'product.key', 'sync'],
            'smart' => ['/product/smart-url', 'Product', 'product.key', 'smart'],
            'privacy' => ['/privacy-policy', 'Legal', 'legal.key', 'privacy'],
            'notice' => ['/aviso-legal', 'Legal', 'legal.key', 'notice'],
            'cookies' => ['/politica-de-cookies', 'Legal', 'legal.key', 'cookies'],
        ];
    }

    #[DataProvider('pages')]
    public function test_editorial_pages_never_share_private_or_commercial_account_state(string $path, string $component, string $prop, string $value): void
    {
        $assertPage = fn (Assert $page) => $page
            ->component('public/'.$component)
            ->where($prop, $value)
            ->missing('auth')
            ->missing('sidebarOpen')
            ->missing('tenant')
            ->missing('workspaces')
            ->missing('documents')
            ->missing('grants')
            ->missing('cart')
            ->missing('orders');

        $this->get($path)->assertOk()->assertViewIs('public')->assertInertia($assertPage);

        $user = User::factory()->create();
        $this->actingAs($user)->get($path)->assertOk()->assertInertia($assertPage)->assertDontSee($user->email);
    }

    public function test_product_destinations_and_unknown_prices_cannot_be_overridden_by_request_input(): void
    {
        Http::preventStrayRequests();

        foreach (['distribucion-vevo' => 2, 'sincronizacion' => 7, 'smart-url' => 1] as $slug => $fields) {
            $query = http_build_query(['product' => 'other', 'purchaseUrl' => 'https://untrusted.invalid', 'file' => '../../.env', 'add-to-cart' => '123']);

            $this->get('/product/'.$slug.'?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('public/Product')
                ->where('product.purchaseUrl', 'https://ascaniopub.com/product/'.$slug.'/')
                ->has('product.fields', $fields)
                ->missing('cart')
                ->missing('order'));
        }

        $this->get('/product/smart-url')->assertInertia(fn (Assert $page) => $page->where('product.price', null));
        $this->get('/product/not-allowed')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_no_local_contact_order_or_commercial_login_is_created(): void
    {
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();

        foreach (['/contacto', '/shop', '/product/distribucion-vevo', '/product/sincronizacion', '/product/smart-url'] as $path) {
            $this->post($path, ['email' => 'synthetic@example.test', 'quantity' => 1])->assertStatus(405);
        }

        foreach (['/cart', '/checkout', '/my-account', '/request-quote'] as $path) {
            $this->get($path)->assertNotFound();
        }

        $this->get('/contacto?email=synthetic%40example.test')->assertInertia(fn (Assert $page) => $page
            ->has('contact.fields', 3)
            ->missing('contact.fields.0.value')
            ->missing('submitted'));

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_legal_copies_preserve_review_and_numbering_without_unapproved_links_or_active_markup(): void
    {
        $allowedTags = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'br', 'a', 'h2', 'h3', 'h4', 'blockquote'];
        $allowedLinks = ['/', 'mailto:support@ascaniopub.com', 'https://aepd.es/', 'https://support.google.com/accounts/answer/61416?hl=es'];

        foreach (['privacy-policy' => 10, 'aviso-legal' => 13, 'politica-de-cookies' => 5] as $path => $lastSection) {
            $response = $this->get('/'.$path.'?'.http_build_query(['reviewRequired' => false, 'file' => '../../.env']));
            $response->assertOk()->assertDontSee('ceporros.com')->assertDontSee('presencialismo.com')
                ->assertInertia(fn (Assert $page) => $page->where('legal.reviewRequired', true));

            $dom = new DOMDocument;
            $dom->loadHTML('<html><body>'.$response->viewData('page')['props']['legal']['html'].'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $unexpected = [];
            $starts = [];

            foreach ((new DOMXPath($dom))->query('//body//*') as $node) {
                if (! in_array($node->nodeName, $allowedTags, true)) {
                    $unexpected[] = $node->nodeName;
                }

                foreach ($node->attributes as $attribute) {
                    if ($node->nodeName === 'a' && $attribute->name === 'href' && in_array($attribute->value, $allowedLinks, true)) {
                        continue;
                    }

                    if ($node->nodeName === 'ol' && $attribute->name === 'start' && preg_match('/^[1-9][0-9]{0,2}$/', $attribute->value)) {
                        $starts[] = (int) $attribute->value;

                        continue;
                    }

                    $unexpected[] = $node->nodeName.'.'.$attribute->name;
                }
            }

            $this->assertSame([], $unexpected);
            $this->assertContains($lastSection, $starts);
        }
    }
}
