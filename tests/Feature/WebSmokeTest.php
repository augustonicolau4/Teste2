<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Document;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test end-to-end das rotas web e da renderização das views.
 *
 * Cobre: autenticação, painel, documentos (HTML/PDF A4/Ticket), oficina/OS,
 * clientes, produtos, relatórios (HTML/PDF/Excel), SAF-T AO e RBAC.
 */
class WebSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@autogest.co.ao')->firstOrFail();
    }

    public function test_guest_é_redirecionado_para_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('autogest_nicola');
    }

    public function test_autenticação_com_sucesso(): void
    {
        $this->post(route('login.attempt'), [
            'email'    => 'admin@autogest.co.ao',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_painel_carrega(): void
    {
        $this->actingAs($this->admin())->get('/')->assertOk()->assertSee('Painel de Controlo');
    }

    public function test_listagem_de_documentos(): void
    {
        $this->actingAs($this->admin())->get(route('documents.index'))
            ->assertOk()->assertSee('Documentos Fiscais');
    }

    public function test_formulário_de_emissão(): void
    {
        $this->actingAs($this->admin())->get(route('documents.create'))
            ->assertOk()->assertSee('Emitir Documento Fiscal');
    }

    public function test_detalhe_do_documento(): void
    {
        $document = Document::query()->firstOrFail();

        $this->actingAs($this->admin())->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee($document->number)
            ->assertSee($document->footerSignature());
    }

    public function test_pdf_a4_e_ticket(): void
    {
        $document = Document::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('documents.pdf', [$document, 'a4']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin())
            ->get(route('documents.pdf', [$document, 'ticket']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_verificação_pública_por_uuid(): void
    {
        $document = Document::query()->firstOrFail();

        $this->get(route('documents.public', $document->uuid))
            ->assertOk()
            ->assertSee($document->number);
    }

    public function test_oficina_listagem_e_detalhe(): void
    {
        $this->actingAs($this->admin())->get(route('service-orders.index'))
            ->assertOk()->assertSee('Ordens de Serviço');

        $order = \App\Domain\Workshop\Models\ServiceOrder::query()->firstOrFail();

        $this->actingAs($this->admin())->get(route('service-orders.show', $order))
            ->assertOk()->assertSee($order->number);

        $this->actingAs($this->admin())->get(route('service-orders.create'))
            ->assertOk()->assertSee('Abrir Ordem de Serviço');
    }

    public function test_clientes_e_produtos(): void
    {
        $this->actingAs($this->admin())->get(route('customers.index'))
            ->assertOk()->assertSee('Clientes e Frotas');

        $this->actingAs($this->admin())->get(route('products.index'))
            ->assertOk()->assertSee('Produtos & Stock');
    }

    public function test_relatórios_html(): void
    {
        $keys = ['faturacao', 'faturacao-utilizador', 'impostos', 'produtividade', 'stock', 'conta-corrente', 'caixa'];

        foreach ($keys as $key) {
            $this->actingAs($this->admin())
                ->get(route('reports.show', $key))
                ->assertOk();
        }
    }

    public function test_relatório_pdf_e_excel(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.pdf', 'impostos'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin())
            ->get(route('reports.excel', 'faturacao'))
            ->assertOk();
    }

    public function test_exportação_saft(): void
    {
        $response = $this->actingAs($this->admin())->get(route('saft.export'));

        $response->assertOk();
        $this->assertStringContainsString('urn:OECD:StandardAuditFile-Tax:AO_1.01_01', $response->getContent());
    }

    public function test_rbac_promotor_sem_acesso_a_produtos(): void
    {
        $promotor = User::query()->where('email', 'promotor@autogest.co.ao')->firstOrFail();

        $this->actingAs($promotor)->get(route('products.index'))->assertForbidden();
        $this->actingAs($promotor)->get(route('documents.index'))->assertOk();
    }
}
