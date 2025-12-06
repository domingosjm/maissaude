<?php
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// HTML do Manual
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            margin: 30px;
            color: #333;
        }
        .cover {
            text-align: center;
            padding: 100px 0;
            page-break-after: always;
        }
        .cover h1 {
            font-size: 36px;
            color: #10b981;
            margin-bottom: 20px;
        }
        .cover h2 {
            font-size: 24px;
            color: #666;
            margin: 10px 0;
        }
        .cover .version {
            margin-top: 50px;
            font-size: 14px;
            color: #999;
        }
        h1 {
            color: #10b981;
            font-size: 22px;
            border-bottom: 3px solid #10b981;
            padding-bottom: 8px;
            margin-top: 30px;
            page-break-after: avoid;
        }
        h2 {
            color: #059669;
            font-size: 18px;
            margin-top: 25px;
            page-break-after: avoid;
        }
        h3 {
            color: #047857;
            font-size: 14px;
            margin-top: 20px;
            page-break-after: avoid;
        }
        .toc {
            page-break-after: always;
        }
        .toc h1 {
            text-align: center;
        }
        .toc-item {
            margin: 8px 0;
            padding-left: 20px;
        }
        .toc-item.level1 {
            font-weight: bold;
            margin-top: 15px;
            padding-left: 0;
        }
        .toc-item.level2 {
            padding-left: 20px;
        }
        .toc-item.level3 {
            padding-left: 40px;
            font-size: 10px;
        }
        .note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 10px;
            margin: 15px 0;
        }
        .warning {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 10px;
            margin: 15px 0;
        }
        .tip {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 10px;
            margin: 15px 0;
        }
        .step {
            background: #f3f4f6;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .step-number {
            display: inline-block;
            background: #10b981;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            margin-right: 10px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background: #10b981;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .module-card {
            background: #f9fafb;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
        }
        .module-card h3 {
            margin-top: 0;
            color: #10b981;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            right: 30px;
            font-size: 9px;
            color: #999;
        }
        ul, ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin: 5px 0;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
            font-size: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <!-- CAPA -->
    <div class="cover">
        <h1>MANUAL DO USUÁRIO</h1>
        <h2>Sistema Integrado Mais Saúde</h2>
        <h2>MSLDA</h2>
        <div style="margin-top: 80px;">
            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI0MCIgZmlsbD0iIzEwYjk4MSIvPgogIDx0ZXh0IHg9IjUwIiB5PSI1OCIgZm9udC1zaXplPSIzNSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC13ZWlnaHQ9ImJvbGQiPk08L3RleHQ+Cjwvc3ZnPg==" alt="Logo">
        </div>
        <div class="version">
            <p><strong>Versão 1.0</strong></p>
            <p>Desenvolvido por Domingos João Mangação</p>
            <p>© 2025 Mais Saúde - Todos os direitos reservados</p>
        </div>
    </div>

    <!-- ÍNDICE -->
    <div class="toc">
        <h1>ÍNDICE</h1>
        <div class="toc-item level1">1. INTRODUÇÃO</div>
        <div class="toc-item level2">1.1 Sobre o Sistema</div>
        <div class="toc-item level2">1.2 Requisitos do Sistema</div>
        <div class="toc-item level2">1.3 Acesso ao Sistema</div>
        
        <div class="toc-item level1">2. DASHBOARD</div>
        <div class="toc-item level2">2.1 Visão Geral</div>
        <div class="toc-item level2">2.2 Módulos Disponíveis</div>
        
        <div class="toc-item level1">3. RECEPÇÃO</div>
        <div class="toc-item level2">3.1 Cadastro de Pacientes</div>
        <div class="toc-item level2">3.2 Agendamento de Consultas</div>
        <div class="toc-item level2">3.3 Ficha do Paciente</div>
        
        <div class="toc-item level1">4. CONSULTAS MÉDICAS</div>
        <div class="toc-item level2">4.1 Dashboard Médico</div>
        <div class="toc-item level2">4.2 Prescrição de Medicamentos</div>
        <div class="toc-item level2">4.3 Solicitação de Exames</div>
        
        <div class="toc-item level1">5. LABORATÓRIO</div>
        <div class="toc-item level2">5.1 Solicitações de Exames</div>
        <div class="toc-item level2">5.2 Registro de Resultados</div>
        <div class="toc-item level2">5.3 Liberação de Exames</div>
        
        <div class="toc-item level1">6. FARMÁCIA</div>
        <div class="toc-item level2">6.1 Gestão de Prescrições</div>
        <div class="toc-item level2">6.2 Gestão de Stock</div>
        <div class="toc-item level2">6.3 Entrega de Medicamentos</div>
        
        <div class="toc-item level1">7. PSICOLOGIA</div>
        <div class="toc-item level2">7.1 Registro de Sessões</div>
        <div class="toc-item level2">7.2 Avaliação Psicológica</div>
        <div class="toc-item level2">7.3 Exportação de Relatórios</div>
        
        <div class="toc-item level1">8. PSIQUIATRIA</div>
        <div class="toc-item level2">8.1 Consultas Psiquiátricas</div>
        <div class="toc-item level2">8.2 Avaliação Mental</div>
        <div class="toc-item level2">8.3 Prescrição Psiquiátrica</div>
        
        <div class="toc-item level1">9. FINANCEIRO</div>
        <div class="toc-item level2">9.1 Gestão de Faturas</div>
        <div class="toc-item level2">9.2 Registro de Pagamentos</div>
        <div class="toc-item level2">9.3 Controle de Despesas</div>
        
        <div class="toc-item level1">10. RELATÓRIOS</div>
        <div class="toc-item level2">10.1 Relatório Financeiro</div>
        <div class="toc-item level2">10.2 Relatório de Consultas</div>
        <div class="toc-item level2">10.3 Exportação em PDF</div>
        
        <div class="toc-item level1">11. CONFIGURAÇÕES</div>
        <div class="toc-item level2">11.1 Configurações Gerais</div>
        <div class="toc-item level2">11.2 Gestão de Usuários</div>
        <div class="toc-item level2">11.3 Serviços e Preços</div>
    </div>

    <!-- CAPÍTULO 1: INTRODUÇÃO -->
    <h1>1. INTRODUÇÃO</h1>
    
    <h2>1.1 Sobre o Sistema</h2>
    <p>O <strong>Sistema Integrado Mais Saúde (MSLDA)</strong> é uma plataforma completa para gestão de clínicas e centros de saúde, desenvolvida para otimizar processos administrativos e clínicos.</p>
    
    <div class="tip">
        <strong>💡 Objetivo:</strong> Integrar todos os setores da clínica em uma única plataforma, desde a recepção até o financeiro, proporcionando eficiência e qualidade no atendimento.
    </div>
    
    <h3>Principais Funcionalidades:</h3>
    <ul>
        <li>Cadastro completo de pacientes</li>
        <li>Agendamento de consultas</li>
        <li>Prontuário eletrônico</li>
        <li>Prescrição médica digital</li>
        <li>Solicitação e gestão de exames</li>
        <li>Controle de farmácia e stock</li>
        <li>Atendimento psicológico e psiquiátrico</li>
        <li>Gestão financeira completa</li>
        <li>Relatórios gerenciais</li>
        <li>Faturamento automático</li>
    </ul>
    
    <h2>1.2 Requisitos do Sistema</h2>
    <table>
        <tr>
            <th style="width: 30%;">Componente</th>
            <th>Requisito</th>
        </tr>
        <tr>
            <td>Navegador</td>
            <td>Google Chrome, Firefox, Edge ou Safari (versões atualizadas)</td>
        </tr>
        <tr>
            <td>Conexão</td>
            <td>Internet banda larga (mínimo 2 Mbps)</td>
        </tr>
        <tr>
            <td>Resolução</td>
            <td>Mínimo 1366x768 pixels</td>
        </tr>
        <tr>
            <td>Sistema Operacional</td>
            <td>Windows 10+, macOS 10.14+, Linux</td>
        </tr>
    </table>
    
    <h2>1.3 Acesso ao Sistema</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Acesse o endereço do sistema no navegador
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Insira seu <strong>e-mail</strong> e <strong>senha</strong> fornecidos pelo administrador
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Clique em <strong>"Entrar"</strong>
    </div>
    
    <div class="warning">
        <strong>⚠️ Segurança:</strong> Não compartilhe suas credenciais de acesso. Faça logout sempre ao terminar de usar o sistema.
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 2: DASHBOARD -->
    <h1>2. DASHBOARD</h1>
    
    <h2>2.1 Visão Geral</h2>
    <p>O Dashboard é a tela inicial do sistema, apresentando uma visão geral de todos os módulos disponíveis. Aqui você tem acesso rápido a todas as funcionalidades de acordo com seu perfil de usuário.</p>
    
    <h2>2.2 Módulos Disponíveis</h2>
    
    <div class="module-card">
        <h3>Recepção</h3>
        <p>Cadastro de pacientes, agendamento de consultas e gestão da fila de atendimento.</p>
    </div>
    
    <div class="module-card">
        <h3>Consultas Médicas</h3>
        <p>Dashboard médico para atendimento, prescrições e solicitação de exames.</p>
    </div>
    
    <div class="module-card">
        <h3>Laboratório</h3>
        <p>Gestão de exames laboratoriais, registro e liberação de resultados.</p>
    </div>
    
    <div class="module-card">
        <h3>Farmácia</h3>
        <p>Controle de prescrições, gestão de stock e entrega de medicamentos.</p>
    </div>
    
    <div class="module-card">
        <h3>Psicologia</h3>
        <p>Registro de sessões psicológicas, avaliações e planos de tratamento.</p>
    </div>
    
    <div class="module-card">
        <h3>Financeiro</h3>
        <p>Gestão de faturas, pagamentos, despesas e fluxo de caixa.</p>
    </div>
    
    <div class="module-card">
        <h3>Relatórios</h3>
        <p>Relatórios gerenciais financeiros, de consultas e operacionais.</p>
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 3: RECEPÇÃO -->
    <h1>3. RECEPÇÃO</h1>
    
    <h2>3.1 Cadastro de Pacientes</h2>
    <p>O cadastro de pacientes é o primeiro passo para iniciar qualquer atendimento no sistema.</p>
    
    <h3>Como Cadastrar um Novo Paciente:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        No Dashboard, clique em <strong>"Recepção"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique no botão <strong>"Cadastrar Novo Paciente"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Preencha os dados obrigatórios:
        <ul>
            <li><strong>Nome Completo</strong></li>
            <li><strong>Data de Nascimento</strong></li>
            <li><strong>Gênero</strong></li>
            <li><strong>Contacto (telefone)</strong></li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Preencha os dados opcionais (endereço, e-mail, etc.)
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Cadastrar Paciente"</strong>
    </div>
    
    <div class="note">
        <strong>Nota:</strong> Após o cadastro, o sistema gera automaticamente um código único para o paciente (formato: MS-XXXX). Este código é usado para identificação em todos os módulos.
    </div>
    
    <h2>3.2 Agendamento de Consultas</h2>
    
    <h3>Tipos de Serviços Disponíveis:</h3>
    <ul>
        <li><strong>Laboratório:</strong> Exames laboratoriais sem consulta médica</li>
        <li><strong>Clínica Geral:</strong> Consultas médicas gerais</li>
        <li><strong>Psicologia/Psiquiatria:</strong> Atendimento de saúde mental</li>
    </ul>
    
    <h3>Processo de Agendamento:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Busque o paciente pelo nome ou código MS-XXXX
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique em <strong>"Agendar Consulta"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Selecione o <strong>Tipo de Serviço</strong>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Escolha o <strong>Profissional</strong> (se aplicável)
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Defina <strong>Data e Hora</strong> da consulta
    </div>
    
    <div class="step">
        <span class="step-number">6</span>
        Clique em <strong>"Agendar"</strong>
    </div>
    
    <h2>3.3 Ficha do Paciente</h2>
    <p>A ficha do paciente reúne todo o histórico de atendimentos em um único lugar.</p>
    
    <h3>Informações Disponíveis:</h3>
    <ul>
        <li>Histórico de consultas</li>
        <li>Exames realizados e resultados</li>
        <li>Sessões de psicologia/psiquiatria</li>
        <li>Prescrições médicas</li>
        <li>Faturas e pagamentos</li>
    </ul>

    <div class="page-break"></div>

    <!-- CAPÍTULO 4: CONSULTAS MÉDICAS -->
    <h1>4. CONSULTAS MÉDICAS</h1>
    
    <h2>4.1 Dashboard Médico</h2>
    <p>O dashboard médico é a interface principal para profissionais de saúde realizarem atendimentos.</p>
    
    <h3>Funcionalidades:</h3>
    <ul>
        <li>Visualização de consultas agendadas</li>
        <li>Prescrição rápida de medicamentos</li>
        <li>Solicitação de exames laboratoriais</li>
        <li>Registro de observações clínicas</li>
    </ul>
    
    <h2>4.2 Prescrição de Medicamentos</h2>
    
    <h3>Prescrição Rápida (sem atendimento completo):</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Nas <strong>"Consultas Agendadas"</strong>, localize o paciente
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique no botão <strong>"Prescrição"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Selecione os medicamentos desejados
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Preencha <strong>dosagem</strong> e <strong>instruções de uso</strong>
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Criar Prescrição"</strong>
    </div>
    
    <div class="tip">
        <strong>Dica:</strong> A prescrição fica disponível imediatamente na farmácia para dispensação.
    </div>
    
    <h2>4.3 Solicitação de Exames</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Clique em <strong>"Exames"</strong> na consulta do paciente
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Selecione os exames necessários (múltipla escolha)
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Clique em <strong>"Solicitar Exames"</strong>
    </div>
    
    <div class="note">
        <strong>Nota:</strong> Os exames solicitados aparecem automaticamente no módulo Laboratório para coleta e processamento.
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 5: LABORATÓRIO -->
    <h1>5. LABORATÓRIO</h1>
    
    <h2>5.1 Solicitações de Exames</h2>
    <p>O módulo Laboratório gerencia todas as solicitações de exames, desde a coleta até a liberação dos resultados.</p>
    
    <h3>Visualização de Solicitações:</h3>
    <ul>
        <li><strong>Pendentes:</strong> Exames aguardando coleta</li>
        <li><strong>Em Processamento:</strong> Amostras coletadas, aguardando resultados</li>
        <li><strong>Liberados:</strong> Resultados prontos e disponíveis</li>
    </ul>
    
    <h2>5.2 Registro de Resultados</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Localize o exame na lista de solicitações
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique em <strong>"Registrar Resultado"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Digite o <strong>resultado do exame</strong>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Adicione <strong>observações</strong> se necessário
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Salvar Resultado"</strong>
    </div>
    
    <h2>5.3 Liberação de Exames</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Após registrar o resultado, clique em <strong>"Liberar Exame"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        O exame fica disponível para visualização e exportação em PDF
    </div>
    
    <div class="warning">
        <strong>Importante:</strong> Só libere exames após validação completa dos resultados. Exames liberados ficam visíveis para médicos e pacientes.
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 6: FARMÁCIA -->
    <h1>6. FARMÁCIA</h1>
    
    <h2>6.1 Gestão de Prescrições</h2>
    <p>A farmácia recebe automaticamente todas as prescrições médicas criadas no sistema.</p>
    
    <h3>Busca de Prescrições:</h3>
    <div class="step">
        <span class="step-number">1</span>
        Use a <strong>barra de busca</strong> para localizar por nome do paciente ou código MS-XXXX
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        As prescrições são agrupadas por paciente
    </div>
    
    <h2>6.2 Gestão de Stock</h2>
    <p>O sistema possui controle completo de estoque de medicamentos.</p>
    
    <h3>Adicionar Novo Medicamento:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Acesse a aba <strong>"Gestão de Stock"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique em <strong>"Adicionar Medicamento"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Preencha o <strong>nome do medicamento</strong>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Informe a <strong>quantidade inicial</strong> em stock
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Adicionar"</strong>
    </div>
    
    <div class="tip">
        <strong>Estatísticas:</strong> O painel mostra total de medicamentos, unidades em stock e alertas de stock baixo (<10 unidades).
    </div>
    
    <h2>6.3 Entrega de Medicamentos</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Localize a prescrição do paciente
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Verifique os medicamentos prescritos
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Clique em <strong>"Marcar como Entregue"</strong>
    </div>
    
    <div class="note">
        <strong>Automação:</strong> Ao entregar medicamentos:
        <ul>
            <li>Sistema deduz automaticamente o stock</li>
            <li>Gera fatura automática na conta do paciente</li>
            <li>Disponibiliza botão "Imprimir Fatura"</li>
        </ul>
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 7: PSICOLOGIA -->
    <h1>7. PSICOLOGIA</h1>
    
    <h2>7.1 Registro de Sessões</h2>
    <p>O módulo de Psicologia permite registro completo de sessões terapêuticas.</p>
    
    <h3>Criar Nova Sessão:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Na lista de consultas agendadas, clique em <strong>"Iniciar Sessão"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Preencha os campos:
        <ul>
            <li><strong>Observações da Sessão:</strong> Anotações da consulta</li>
            <li><strong>Diagnóstico:</strong> Avaliação clínica</li>
            <li><strong>Plano de Tratamento:</strong> Intervenções propostas</li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Defina a <strong>Data da Próxima Sessão</strong> (opcional)
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Clique em <strong>"Salvar Sessão"</strong>
    </div>
    
    <h2>7.2 Avaliação Psicológica</h2>
    <p>Cada sessão registrada pode incluir avaliações detalhadas do estado emocional e comportamental do paciente.</p>
    
    <h2>7.3 Exportação de Relatórios</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        No histórico de atendimentos, clique em <strong>"Exportar PDF"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        O sistema gera um relatório completo com:
        <ul>
            <li>Dados do paciente</li>
            <li>Observações da sessão</li>
            <li>Diagnóstico</li>
            <li>Plano de tratamento</li>
            <li>Assinatura do profissional</li>
        </ul>
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 8: PSIQUIATRIA -->
    <h1>8. PSIQUIATRIA</h1>
    
    <h2>8.1 Consultas Psiquiátricas</h2>
    <p>Similar ao módulo de Psicologia, mas com foco em tratamento medicamentoso.</p>
    
    <h2>8.2 Avaliação Mental</h2>
    
    <h3>Registro de Consulta:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Clique em <strong>"Iniciar Atendimento"</strong> → <strong>"Atendimento Completo"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Preencha:
        <ul>
            <li><strong>Observações:</strong> Queixa principal e histórico</li>
            <li><strong>Diagnóstico:</strong> CID e avaliação clínica</li>
            <li><strong>Avaliação do Estado Mental:</strong> Exame psíquico detalhado</li>
        </ul>
    </div>
    
    <h2>8.3 Prescrição Psiquiátrica</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Na mesma tela, vá para <strong>"Prescrever Medicamentos"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Selecione medicamentos psiquiátricos
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Defina dosagem e instruções específicas
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Clique em <strong>"Salvar Prescrição"</strong>
    </div>
    
    <div class="note">
        <strong>Nota:</strong> O relatório exportado em PDF inclui automaticamente a lista de medicamentos prescritos.
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 9: FINANCEIRO -->
    <h1>9. FINANCEIRO</h1>
    
    <h2>9.1 Gestão de Faturas</h2>
    <p>O sistema gera faturas automaticamente em diversos cenários:</p>
    
    <ul>
        <li>Após agendamento de consulta (fatura de serviço)</li>
        <li>Entrega de medicamentos na farmácia</li>
        <li>Realização de exames laboratoriais</li>
    </ul>
    
    <h3>Visualizar Faturas:</h3>
    <div class="step">
        <span class="step-number">1</span>
        Acesse o módulo <strong>"Financeiro"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Veja lista de faturas com status:
        <ul>
            <li><strong>Pendente:</strong> Aguardando pagamento</li>
            <li><strong>Paga:</strong> Pagamento confirmado</li>
            <li><strong>Parcial:</strong> Pagamento parcial realizado</li>
        </ul>
    </div>
    
    <h2>9.2 Registro de Pagamentos</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Localize a fatura na lista
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique em <strong>"Registrar Pagamento"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Selecione o <strong>Método de Pagamento</strong>:
        <ul>
            <li>Dinheiro</li>
            <li>M-Pesa</li>
            <li>E-Mola</li>
            <li>Transferência Bancária</li>
            <li>Outros</li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Informe o <strong>Valor Pago</strong>
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Adicione <strong>Observações</strong> (opcional)
    </div>
    
    <div class="step">
        <span class="step-number">6</span>
        Clique em <strong>"Confirmar Pagamento"</strong>
    </div>
    
    <h2>9.3 Controle de Despesas</h2>
    
    <h3>Registrar Nova Despesa:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Na aba <strong>"Despesas"</strong>, clique em <strong>"Nova Despesa"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Selecione a <strong>Categoria</strong>:
        <ul>
            <li>Salários</li>
            <li>Fornecedores</li>
            <li>Aluguel</li>
            <li>Utilidades (água, luz, etc.)</li>
            <li>Marketing</li>
            <li>Outros</li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Preencha:
        <ul>
            <li><strong>Descrição</strong></li>
            <li><strong>Valor</strong></li>
            <li><strong>Data</strong></li>
            <li><strong>Método de Pagamento</strong></li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Defina o <strong>Status</strong>: Paga ou Pendente
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Registrar Despesa"</strong>
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 10: RELATÓRIOS -->
    <h1>10. RELATÓRIOS</h1>
    
    <h2>10.1 Relatório Financeiro</h2>
    <p>Análise completa do desempenho financeiro da clínica.</p>
    
    <h3>Informações Incluídas:</h3>
    <ul>
        <li><strong>Total Faturado:</strong> Valor total de faturas emitidas</li>
        <li><strong>Total Recebido:</strong> Pagamentos confirmados</li>
        <li><strong>Total Despesas:</strong> Gastos operacionais</li>
        <li><strong>Saldo do Período:</strong> Receitas - Despesas</li>
        <li><strong>Receitas por Categoria:</strong> Detalhamento por tipo de serviço</li>
        <li><strong>Pagamentos por Método:</strong> Análise dos meios de pagamento</li>
        <li><strong>Despesas por Categoria:</strong> Distribuição de gastos</li>
    </ul>
    
    <h2>10.2 Relatório de Consultas</h2>
    
    <h3>Métricas Disponíveis:</h3>
    <ul>
        <li>Atendimentos por profissional</li>
        <li>Atendimentos por especialidade</li>
        <li>Status de consultas (agendadas, realizadas, canceladas)</li>
        <li>Pacientes únicos atendidos</li>
    </ul>
    
    <h2>10.3 Outros Relatórios</h2>
    
    <div class="module-card">
        <h3>Relatório de Farmácia</h3>
        <ul>
            <li>Top 20 medicamentos mais dispensados</li>
            <li>Valor total de medicamentos faturados</li>
            <li>Alertas de stock crítico</li>
            <li>Stock atual por medicamento</li>
        </ul>
    </div>
    
    <div class="module-card">
        <h3>Relatório de Laboratório</h3>
        <ul>
            <li>Exames mais solicitados</li>
            <li>Tempo médio de liberação</li>
            <li>Taxa de exames liberados vs pendentes</li>
        </ul>
    </div>
    
    <div class="module-card">
        <h3>Relatório de Saúde Mental</h3>
        <ul>
            <li>Sessões de psicologia realizadas</li>
            <li>Consultas de psiquiatria</li>
            <li>Taxa de retorno de pacientes</li>
            <li>Prescrições psiquiátricas</li>
        </ul>
    </div>
    
    <h2>10.4 Exportação em PDF</h2>
    
    <div class="step">
        <span class="step-number">1</span>
        Selecione o <strong>Tipo de Relatório</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Defina <strong>Data Início</strong> e <strong>Data Fim</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Clique em <strong>"Filtrar"</strong> para visualizar
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Clique no botão <strong>"PDF"</strong> para exportar
    </div>
    
    <div class="tip">
        <strong>Dica:</strong> O relatório financeiro em PDF possui layout profissional, ideal para apresentações e auditorias.
    </div>

    <div class="page-break"></div>

    <!-- CAPÍTULO 11: CONFIGURAÇÕES -->
    <h1>11. CONFIGURAÇÕES</h1>
    
    <h2>11.1 Configurações Gerais</h2>
    <p>Personalize o sistema de acordo com as necessidades da clínica.</p>
    
    <h3>Informações da Clínica:</h3>
    <ul>
        <li>Nome da clínica</li>
        <li>Endereço completo</li>
        <li>Telefone e e-mail</li>
        <li>Logo (opcional)</li>
    </ul>
    
    <h2>11.2 Gestão de Usuários</h2>
    
    <h3>Criar Novo Usuário:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Acesse <strong>"Configurações"</strong> → <strong>"Usuários"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Clique em <strong>"Adicionar Usuário"</strong>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Preencha:
        <ul>
            <li><strong>Nome</strong></li>
            <li><strong>E-mail</strong> (usado como login)</li>
            <li><strong>Senha</strong></li>
            <li><strong>Cargo/Função</strong></li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Defina <strong>Permissões de Acesso</strong> aos módulos
    </div>
    
    <div class="step">
        <span class="step-number">5</span>
        Clique em <strong>"Criar Usuário"</strong>
    </div>
    
    <div class="warning">
        <strong>Segurança:</strong> Conceda apenas as permissões necessárias para cada função. Evite criar múltiplos usuários administrativos.
    </div>
    
    <h2>11.3 Serviços e Preços</h2>
    
    <h3>Gerenciar Serviços:</h3>
    
    <div class="step">
        <span class="step-number">1</span>
        Acesse <strong>"Serviços"</strong> nas Configurações
    </div>
    
    <div class="step">
        <span class="step-number">2</span>
        Visualize serviços por categoria:
        <ul>
            <li>Consultas</li>
            <li>Exames</li>
            <li>Medicamentos</li>
            <li>Procedimentos</li>
        </ul>
    </div>
    
    <div class="step">
        <span class="step-number">3</span>
        Para editar, clique no serviço e altere <strong>nome</strong> ou <strong>preço</strong>
    </div>
    
    <div class="step">
        <span class="step-number">4</span>
        Para adicionar, clique em <strong>"Novo Serviço"</strong>
    </div>
    
    <div class="note">
        <strong>Nota:</strong> Alterações de preços só afetam novas faturas. Faturas existentes mantêm os valores originais.
    </div>

    <div class="page-break"></div>

    <!-- PERGUNTAS FREQUENTES -->
    <h1>12. PERGUNTAS FREQUENTES (FAQ)</h1>
    
    <h3>Como recuperar minha senha?</h3>
    <p>Entre em contato com o administrador do sistema para redefinição de senha.</p>
    
    <h3>Posso editar uma fatura já emitida?</h3>
    <p>Não. Faturas emitidas não podem ser editadas por questões de auditoria. É possível cancelar e criar uma nova fatura se necessário.</p>
    
    <h3>Como funciona o faturamento automático de medicamentos?</h3>
    <p>Quando o farmacêutico marca uma prescrição como "Entregue", o sistema automaticamente:
    <ul>
        <li>Gera uma fatura com os medicamentos dispensados</li>
        <li>Deduz o stock correspondente</li>
        <li>Disponibiliza a fatura para pagamento</li>
    </ul>
    </p>
    
    <h3>Posso exportar dados para Excel?</h3>
    <p>Atualmente o sistema exporta relatórios em PDF. Funcionalidade de exportação Excel está em desenvolvimento.</p>
    
    <h3>O que acontece se o stock de um medicamento acabar?</h3>
    <p>O sistema exibe alerta de "Stock Crítico" quando a quantidade está abaixo de 10 unidades. É importante reabastecer regularmente.</p>
    
    <h3>Como visualizar o histórico completo de um paciente?</h3>
    <p>Na Recepção, busque o paciente e clique no nome para acessar a ficha completa com todas as consultas, exames e prescrições.</p>
    
    <h3>Posso ter múltiplos profissionais usando o sistema simultaneamente?</h3>
    <p>Sim. O sistema suporta múltiplos usuários simultâneos sem conflitos.</p>

    <div class="page-break"></div>

    <!-- SUPORTE TÉCNICO -->
    <h1>13. SUPORTE TÉCNICO</h1>
    
    <h2>Canais de Suporte</h2>
    
    <div class="module-card">
        <h3>E-mail</h3>
        <p>suporte@maissaude.co.mz</p>
        <p><em>Tempo de resposta: até 24 horas</em></p>
    </div>
    
    <div class="module-card">
        <h3>Telefone</h3>
        <p>+258 834339074</p>
        <p><em>Horário: Segunda a Sexta, 8h às 17h</em></p>
    </div>
    
    <h2>Tipos de Suporte</h2>
    
    <table>
        <tr>
            <th>Tipo</th>
            <th>Descrição</th>
            <th>Prioridade</th>
        </tr>
        <tr>
            <td>Crítico</td>
            <td>Sistema indisponível, perda de dados</td>
            <td>Resposta imediata</td>
        </tr>
        <tr>
            <td>Urgente</td>
            <td>Funcionalidade essencial não funciona</td>
            <td>Até 2 horas</td>
        </tr>
        <tr>
            <td>Normal</td>
            <td>Dúvidas, problemas menores</td>
            <td>Até 24 horas</td>
        </tr>
        <tr>
            <td>Baixa</td>
            <td>Sugestões, melhorias</td>
            <td>Até 48 horas</td>
        </tr>
    </table>
    
    <h2>Informações para Relatar Problemas</h2>
    <p>Ao entrar em contato com o suporte, forneça:</p>
    <ul>
        <li>Descrição detalhada do problema</li>
        <li>Módulo onde ocorreu o erro</li>
        <li>Mensagem de erro (se houver)</li>
        <li>Passos para reproduzir o problema</li>
        <li>Navegador e versão utilizada</li>
        <li>Capturas de tela (se aplicável)</li>
    </ul>

    <div class="page-break"></div>

    <!-- GLOSSÁRIO -->
    <h1>14. GLOSSÁRIO</h1>
    
    <table>
        <tr>
            <th style="width: 30%;">Termo</th>
            <th>Definição</th>
        </tr>
        <tr>
            <td><strong>Atendimento</strong></td>
            <td>Registro de consulta ou sessão realizada com um paciente</td>
        </tr>
        <tr>
            <td><strong>Código MS-XXXX</strong></td>
            <td>Código único gerado para cada paciente no formato MS-0001, MS-0002, etc.</td>
        </tr>
        <tr>
            <td><strong>Dashboard</strong></td>
            <td>Tela inicial com visão geral e acesso aos módulos do sistema</td>
        </tr>
        <tr>
            <td><strong>Fatura</strong></td>
            <td>Documento que registra serviços prestados e valores a pagar</td>
        </tr>
        <tr>
            <td><strong>Módulo</strong></td>
            <td>Seção específica do sistema (Recepção, Farmácia, etc.)</td>
        </tr>
        <tr>
            <td><strong>Prescrição</strong></td>
            <td>Documento médico com indicação de medicamentos e instruções de uso</td>
        </tr>
        <tr>
            <td><strong>Prontuário</strong></td>
            <td>Conjunto completo de informações médicas de um paciente</td>
        </tr>
        <tr>
            <td><strong>Stock</strong></td>
            <td>Quantidade disponível de medicamentos no estoque da farmácia</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <!-- CONCLUSÃO -->
    <h1>15. CONCLUSÃO</h1>
    
    <p>O <strong>Sistema Integrado Mais Saúde (MSLDA)</strong> foi desenvolvido para otimizar todos os processos de uma clínica moderna, desde o primeiro contato com o paciente até o faturamento e análise gerencial.</p>
    
    <h2>Benefícios do Sistema</h2>
    
    <div class="module-card">
        <h3>Eficiência Operacional</h3>
        <p>Redução de tempo em tarefas administrativas, permitindo foco no atendimento ao paciente.</p>
    </div>
    
    <div class="module-card">
        <h3>Integração Total</h3>
        <p>Todos os setores conectados, eliminando retrabalho e duplicação de informações.</p>
    </div>
    
    <div class="module-card">
        <h3>Controle Financeiro</h3>
        <p>Faturamento automático e relatórios detalhados para melhor gestão de receitas e despesas.</p>
    </div>
    
    <div class="module-card">
        <h3>Segurança de Dados</h3>
        <p>Informações dos pacientes armazenadas com segurança e controle de acesso por perfil.</p>
    </div>
    
    <div class="module-card">
        <h3>Tomada de Decisão</h3>
        <p>Relatórios gerenciais facilitam análise de desempenho e planejamento estratégico.</p>
    </div>
    
    <h2>Atualizações</h2>
    <p>Este manual é atualizado regularmente conforme novas funcionalidades são adicionadas ao sistema. Verifique a versão mais recente no menu de Ajuda do sistema.</p>
    
    <div class="tip">
        <strong>Sugestão:</strong> Mantenha este manual acessível para consulta rápida. Recomenda-se treinamento regular da equipe para aproveitar ao máximo todas as funcionalidades.
    </div>
    
    <h2>Agradecimento</h2>
    <p>Agradecemos por escolher o Sistema Mais Saúde. Nossa equipe está comprometida em fornecer a melhor experiência possível para profissionais de saúde e pacientes.</p>
    
    <div style="text-align: center; margin-top: 50px; padding: 30px; background: #f0fdf4; border-radius: 10px;">
        <h2 style="color: #10b981; margin: 0;">Sistema Integrado Mais Saúde</h2>
        <p style="color: #666; margin: 10px 0;">Transformando a gestão de saúde em Moçambique</p>
        <p style="color: #10b981; font-weight: bold; margin-top: 20px;">www.maissaude.co.mz</p>
    </div>
    
</body>
</html>
';

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Enviar o PDF ao navegador
$dompdf->stream('Manual_Usuario_MSLDA_v1.0.pdf', ['Attachment' => true]);
