<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicServicePagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [
            'distribution' => ['/distribucion-digital', 'distribution'],
            'publishing' => ['/publishing', 'publishing'],
            'visual identity' => ['/identidad-visual', 'visual-identity'],
            'web' => ['/desarrollo-web', 'web'],
            'media' => ['/medios-de-comunicacion', 'media'],
            'social' => ['/social-media', 'social'],
            'guide' => ['/tus-primeros-pasos', 'guide'],
        ];
    }

    #[DataProvider('pages')]
    public function test_public_pages_expose_only_editorial_content_with_or_without_a_session(string $path, string $key): void
    {
        $component = $key === 'guide' ? 'public/Guide' : 'public/Service';
        $prop = $key === 'guide' ? 'guide' : 'service';

        $assertPage = fn (Assert $page) => $page
            ->component($component)
            ->where($prop.'.key', $key)
            ->missing('auth')
            ->missing('sidebarOpen')
            ->missing('tenant')
            ->missing('workspaces')
            ->missing('documents')
            ->missing('grants');

        $this->get($path)->assertOk()->assertViewIs('public')->assertInertia($assertPage);

        $user = User::factory()->create();
        $this->actingAs($user)->get($path)->assertOk()->assertInertia($assertPage)->assertDontSee($user->email);
    }

    public function test_request_parameters_cannot_choose_the_editorial_file_or_expose_private_routes(): void
    {
        $this->get('/publishing?'.http_build_query([
            'service' => 'guide', 'file' => '../../.env', 'tenant_id' => 'untrusted',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/Service')
            ->where('service.key', 'publishing')
            ->missing('guide')
            ->missing('tenant_id'));

        $this->get('/services/not-allowed')->assertNotFound();
        $this->get(route('shared-documents.index'))->assertRedirect(route('login'));
    }

    public function test_guide_html_is_versioned_editorial_markup_without_executable_or_unapproved_attributes(): void
    {
        $response = $this->get('/tus-primeros-pasos');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/Guide')
            ->has('guide.sections', 16)
            ->where('guide.sections.0.title', 'Metadatos'));

        $allowed = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'br', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'select', 'option'];
        $unexpected = [];
        $links = [];
        $selectLabels = [];

        foreach ($response->viewData('page')['props']['guide']['sections'] as $section) {
            $dom = new DOMDocument;
            $dom->loadHTML('<html><body>'.$section['html'].'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            foreach ((new DOMXPath($dom))->query('//body//*') as $node) {
                if (! in_array($node->nodeName, $allowed, true)) {
                    $unexpected[] = $node->nodeName;
                }

                foreach ($node->attributes as $attribute) {
                    if ($node->nodeName === 'a' && $attribute->name === 'href') {
                        $links[] = $attribute->value;
                    } elseif ($node->nodeName === 'select' && $attribute->name === 'aria-label') {
                        $selectLabels[] = $attribute->value;
                    } else {
                        $unexpected[] = $node->nodeName.'.'.$attribute->name;
                    }
                }
            }
        }

        $this->assertSame([], $unexpected);
        $this->assertSame(['https://distribution.ascanioagency.com/about/anti_fraud'], array_values(array_unique($links)));
        $this->assertCount(2, $selectLabels);
    }
}
