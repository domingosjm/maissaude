<?php
/**
 * DOCUMENTAÇÃO DO SISTEMA - INTEGRADA MAIS SAÚDE
 * Manual completo de uso e guia de funcionalidades
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuário';
$page_title = 'Documentação';
$module_name = 'Ajuda e Suporte';
include 'includes/header.php';
?>

<style>
    .doc-section { display: none; }
    .doc-section.active { display: block; }
    .doc-nav-item.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    .code-block {
        background: #1e293b;
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
        font-family: 'Courier New', monospace;
    }
    .feature-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .accordion-content.active {
        max-height: 2000px;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="bi bi-book text-emerald-600 mr-3"></i>
                Documentação do Sistema
            </h1>
            <p class="text-gray-600 mt-2">Manual completo de uso - Integrada Mais Saúde</p>
        </div>
        <a href="dashboard.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center">
            <i class="bi bi-arrow-left mr-2"></i> Voltar
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-4 sticky top-20">
                <h3 class="font-semibold text-gray-900 mb-4">Navegação</h3>
                <nav class="space-y-2">
                    <button onclick="showSection('intro')" class="doc-nav-item active w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-house-door mr-2"></i> Introdução
                    </button>
                    <button onclick="showSection('login')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-box-arrow-in-right mr-2"></i> Login e Acesso
                    </button>
                    <button onclick="showSection('recepcao')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-person-check mr-2"></i> Recepção
                    </button>
                    <button onclick="showSection('consultorio')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-heart-pulse mr-2"></i> Consultórios
                    </button>
                    <button onclick="showSection('laboratorio')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-clipboard2-pulse mr-2"></i> Laboratório
                    </button>
                    <button onclick="showSection('farmacia')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-capsule mr-2"></i> Farmácia
                    </button>
                    <button onclick="showSection('financeiro')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-cash-coin mr-2"></i> Financeiro
                    </button>
                    <button onclick="showSection('busca')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-search mr-2"></i> Busca Global
                    </button>
                    <button onclick="showSection('config')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-gear mr-2"></i> Configurações
                    </button>
                    <button onclick="showSection('faq')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-question-circle mr-2"></i> FAQ
                    </button>
                    <button onclick="showSection('suporte')" class="doc-nav-item w-full text-left px-4 py-2 rounded-lg hover:bg-emerald-50 transition">
                        <i class="bi bi-headset mr-2"></i> Suporte
                    </button>
                </nav>
            </div>
        </div>

        <!-- Content Area -->
        <div class="lg:col-span-3">
            
            <!-- INTRODUÇÃO -->
            <div class="doc-section active" id="section-intro">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Bem-vindo ao Sistema Integrada Mais Saúde</h2>
                    <p class="text-gray-700 mb-4">
                        O MSLDA (Medical System Laboratorio e Diagnóstico Avançado) é uma plataforma completa de gestão clínica 
                        desenvolvida para otimizar o fluxo de trabalho em instituições de saúde.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <div class="feature-card bg-gradient-to-br from-emerald-50 to-green-50 p-4 rounded-lg border border-emerald-200 transition">
                            <i class="bi bi-check-circle text-emerald-600 text-3xl mb-2"></i>
                            <h3 class="font-semibold text-gray-900 mb-1">Fácil de Usar</h3>
                            <p class="text-sm text-gray-600">Interface intuitiva e moderna</p>
                        </div>
                        <div class="feature-card bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200 transition">
                            <i class="bi bi-shield-check text-blue-600 text-3xl mb-2"></i>
                            <h3 class="font-semibold text-gray-900 mb-1">Seguro</h3>
                            <p class="text-sm text-gray-600">Dados protegidos e criptografados</p>
                        </div>
                        <div class="feature-card bg-gradient-to-br from-purple-50 to-pink-50 p-4 rounded-lg border border-purple-200 transition">
                            <i class="bi bi-lightning text-purple-600 text-3xl mb-2"></i>
                            <h3 class="font-semibold text-gray-900 mb-1">Rápido</h3>
                            <p class="text-sm text-gray-600">Otimizado para performance</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Módulos Principais</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-person-check text-emerald-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Recepção</h4>
                                    <p class="text-sm text-gray-600">Cadastro de pacientes e agendamento de consultas</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-heart-pulse text-red-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Consultórios</h4>
                                    <p class="text-sm text-gray-600">Atendimento médico, psicologia e psiquiatria</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-clipboard2-pulse text-blue-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Laboratório</h4>
                                    <p class="text-sm text-gray-600">Gestão de exames e resultados laboratoriais</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-capsule text-purple-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Farmácia</h4>
                                    <p class="text-sm text-gray-600">Controle de medicamentos e dispensação</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-cash-coin text-yellow-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Financeiro</h4>
                                    <p class="text-sm text-gray-600">Faturamento e gestão de pagamentos</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <i class="bi bi-search text-gray-600 text-2xl"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Busca Global</h4>
                                    <p class="text-sm text-gray-600">Pesquisa rápida em todo o sistema (Ctrl+K)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOGIN E ACESSO -->
            <div class="doc-section" id="section-login">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Login e Acesso ao Sistema</h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Como Fazer Login</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Acesse a URL: <code class="bg-gray-100 px-2 py-1 rounded">http://localhost/MSLDA/</code></li>
                                <li>Digite seu <strong>e-mail</strong> no campo apropriado</li>
                                <li>Digite sua <strong>senha</strong></li>
                                <li>Clique em <strong>"Entrar"</strong></li>
                            </ol>
                        </div>

                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="bi bi-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-yellow-800">Importante</h4>
                                    <p class="text-yellow-700 text-sm">Altere sua senha padrão no primeiro acesso para garantir a segurança da sua conta.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Perfis de Usuário</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissões</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Admin</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">Acesso total ao sistema, configurações e relatórios</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Recepcionista</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">Cadastro de pacientes, agendamentos e check-in</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Médico</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">Atendimentos, prescrições e solicitação de exames</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Laboratório</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">Registro e validação de resultados de exames</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900">Farmácia</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">Dispensação de medicamentos e controle de estoque</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Recuperação de Senha</h3>
                            <p class="text-gray-700 mb-2">Se esqueceu sua senha, entre em contato com:</p>
                            <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                                <p class="text-gray-800"><strong>Suporte Técnico:</strong> Domingos João Mangação</p>
                                <p class="text-gray-700">E-mail: domingos.mangacao@maissaude.co.mz</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECEPÇÃO -->
            <div class="doc-section" id="section-recepcao">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-person-check text-emerald-600 mr-2"></i>
                        Módulo de Recepção
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Cadastro de Pacientes</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Clique em <strong>"+ Novo Paciente"</strong></li>
                                <li>Preencha os dados obrigatórios:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li>Nome completo</li>
                                        <li>BI (Bilhete de Identidade)</li>
                                        <li>Data de nascimento</li>
                                        <li>Telefone</li>
                                        <li>Endereço</li>
                                    </ul>
                                </li>
                                <li>Clique em <strong>"Salvar"</strong></li>
                                <li>O sistema gera automaticamente um código único (MS-XXXX)</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Agendamento de Consultas</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Selecione o paciente na lista ou busque pelo BI/Nome</li>
                                <li>Clique em <strong>"Agendar Consulta"</strong></li>
                                <li>Escolha:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li>Tipo de consulta (Medicina Geral, Psicologia, Psiquiatria)</li>
                                        <li>Profissional (se necessário)</li>
                                        <li>Data e hora</li>
                                    </ul>
                                </li>
                                <li>Confirme o agendamento</li>
                            </ol>
                        </div>

                        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded">
                            <h4 class="font-semibold text-emerald-800 mb-2">Dica</h4>
                            <p class="text-emerald-700 text-sm">Use a busca global (Ctrl+K) para encontrar rapidamente qualquer paciente!</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Lista de Espera</h3>
                            <p class="text-gray-700 mb-2">A lista de espera mostra todos os pacientes agendados para hoje:</p>
                            <ul class="list-disc list-inside space-y-1 text-gray-700 ml-4">
                                <li><span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Agendado</span> - Aguardando atendimento</li>
                                <li><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Em Atendimento</span> - Sendo atendido</li>
                                <li><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Concluído</span> - Atendimento finalizado</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONSULTÓRIOS -->
            <div class="doc-section" id="section-consultorio">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-heart-pulse text-red-600 mr-2"></i>
                        Módulo de Consultórios
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Realizar Atendimento</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Selecione o paciente na fila de atendimento</li>
                                <li>Clique em <strong>"Iniciar Atendimento"</strong></li>
                                <li>Registre:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li><strong>Anamnese:</strong> Queixa principal e histórico</li>
                                        <li><strong>Exame Físico:</strong> Sinais vitais e observações</li>
                                        <li><strong>Diagnóstico:</strong> CID-10 se aplicável</li>
                                        <li><strong>Conduta:</strong> Plano de tratamento</li>
                                    </ul>
                                </li>
                                <li>Salve o atendimento</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Solicitar Exames</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Durante o atendimento, clique em <strong>"Solicitar Exames"</strong></li>
                                <li>Selecione os exames necessários da lista</li>
                                <li>Adicione observações (se necessário)</li>
                                <li>Confirme a solicitação</li>
                                <li>Os exames aparecem automaticamente no protocolo do laboratório</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Prescrever Medicamentos</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Clique em <strong>"Nova Prescrição"</strong></li>
                                <li>Selecione o medicamento</li>
                                <li>Defina:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li><strong>Dosagem:</strong> Ex: 500mg, 1 comprimido</li>
                                        <li><strong>Frequência:</strong> Ex: 3x ao dia, de 8 em 8 horas</li>
                                        <li><strong>Duração:</strong> Ex: 7 dias, 14 dias</li>
                                        <li><strong>Orientações:</strong> Tomar com água, após refeições, etc.</li>
                                    </ul>
                                </li>
                                <li>A prescrição é enviada automaticamente para a farmácia</li>
                            </ol>
                        </div>

                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                            <h4 class="font-semibold text-red-800 mb-2">Atenção</h4>
                            <p class="text-red-700 text-sm">Sempre verifique alergias medicamentosas antes de prescrever!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LABORATÓRIO -->
            <div class="doc-section" id="section-laboratorio">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-clipboard2-pulse text-blue-600 mr-2"></i>
                        Módulo de Laboratório
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Protocolo do Dia</h3>
                            <p class="text-gray-700 mb-3">O protocolo mostra todos os exames solicitados para o dia atual:</p>
                            <ul class="list-disc list-inside space-y-1 text-gray-700 ml-4">
                                <li><span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pendente</span> - Aguardando coleta/realização</li>
                                <li><span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">Em Análise</span> - Sendo processado</li>
                                <li><span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">Aguardando Validação</span> - Pronto para validar</li>
                                <li><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Validado</span> - Resultado liberado</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Lançar Resultados</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>No protocolo, clique no exame desejado</li>
                                <li>Clique em <strong>"Lançar Resultado"</strong></li>
                                <li>Preencha os valores dos parâmetros</li>
                                <li>O sistema exibe valores de referência automaticamente</li>
                                <li>Adicione observações técnicas (se necessário)</li>
                                <li>Salve o resultado</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Validar Resultados</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Acesse a aba <strong>"Validação"</strong></li>
                                <li>Revise os resultados lançados</li>
                                <li>Verifique valores críticos ou anormais</li>
                                <li>Clique em <strong>"Validar"</strong> para liberar</li>
                                <li>O resultado fica disponível para impressão/visualização</li>
                            </ol>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                            <h4 class="font-semibold text-blue-800 mb-2">Nota Importante</h4>
                            <p class="text-blue-700 text-sm">Apenas resultados validados podem ser impressos e visualizados pelo médico solicitante.</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Imprimir Resultados</h3>
                            <p class="text-gray-700">Clique no ícone <i class="bi bi-printer"></i> ao lado do exame validado para gerar o PDF com logotipo da clínica.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FARMÁCIA -->
            <div class="doc-section" id="section-farmacia">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-capsule text-purple-600 mr-2"></i>
                        Módulo de Farmácia
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Dispensar Medicamentos</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Busque o paciente ou prescrição</li>
                                <li>Visualize a lista de medicamentos prescritos</li>
                                <li>Verifique disponibilidade no estoque</li>
                                <li>Selecione os medicamentos a dispensar</li>
                                <li>Confirme a dispensação</li>
                                <li>O estoque é atualizado automaticamente</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Controle de Estoque</h3>
                            <p class="text-gray-700 mb-3">A farmácia mantém controle rigoroso do inventário:</p>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Entrada:</strong> Registre recebimento de medicamentos</li>
                                <li><strong>Saída:</strong> Dispensação automática com prescrição</li>
                                <li><strong>Alertas:</strong> Sistema avisa quando estoque está baixo</li>
                                <li><strong>Validade:</strong> Alertas de medicamentos próximos ao vencimento</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Adicionar Medicamento ao Estoque</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Clique em <strong>"+ Novo Medicamento"</strong> ou <strong>"Entrada"</strong></li>
                                <li>Preencha os dados:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li>Nome comercial/genérico</li>
                                        <li>Concentração</li>
                                        <li>Quantidade</li>
                                        <li>Lote</li>
                                        <li>Data de validade</li>
                                        <li>Preço unitário</li>
                                    </ul>
                                </li>
                                <li>Salve o registro</li>
                            </ol>
                        </div>

                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                            <h4 class="font-semibold text-yellow-800 mb-2">Alerta de Estoque</h4>
                            <p class="text-yellow-700 text-sm">Configure alertas de estoque mínimo em <strong>Configurações → Farmácia</strong>.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FINANCEIRO -->
            <div class="doc-section" id="section-financeiro">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-cash-coin text-yellow-600 mr-2"></i>
                        Módulo Financeiro
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Faturamento</h3>
                            <p class="text-gray-700 mb-3">As faturas são geradas automaticamente incluindo:</p>
                            <ul class="list-disc list-inside space-y-1 text-gray-700 ml-4">
                                <li>Valor da consulta</li>
                                <li>Exames solicitados</li>
                                <li>Medicamentos dispensados</li>
                                <li>Procedimentos realizados</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Registrar Pagamento</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Acesse <strong>"Faturas Pendentes"</strong></li>
                                <li>Selecione a fatura do paciente</li>
                                <li>Clique em <strong>"Registrar Pagamento"</strong></li>
                                <li>Escolha o método:
                                    <ul class="list-disc list-inside ml-6 mt-2">
                                        <li>Dinheiro</li>
                                        <li>M-Pesa</li>
                                        <li>Mkesh</li>
                                        <li>Cartão</li>
                                        <li>Transferência Bancária</li>
                                    </ul>
                                </li>
                                <li>Informe o valor pago</li>
                                <li>Aplique desconto (se autorizado)</li>
                                <li>Confirme o pagamento</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Relatórios Financeiros</h3>
                            <p class="text-gray-700 mb-3">Gere relatórios detalhados por:</p>
                            <ul class="list-disc list-inside space-y-1 text-gray-700 ml-4">
                                <li><strong>Período:</strong> Dia, semana, mês, ano</li>
                                <li><strong>Método de pagamento:</strong> Dinheiro, M-Pesa, etc.</li>
                                <li><strong>Status:</strong> Pagas, pendentes, vencidas</li>
                                <li><strong>Paciente/Profissional:</strong> Individual</li>
                            </ul>
                        </div>

                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                            <h4 class="font-semibold text-green-800 mb-2">Exportação</h4>
                            <p class="text-green-700 text-sm">Todos os relatórios podem ser exportados em PDF ou Excel.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BUSCA GLOBAL -->
            <div class="doc-section" id="section-busca">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-search text-gray-600 mr-2"></i>
                        Busca Global
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Como Usar</h3>
                            <p class="text-gray-700 mb-3">A busca global permite encontrar rapidamente qualquer informação no sistema:</p>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700">
                                <li>Pressione <kbd class="px-2 py-1 bg-gray-800 text-white rounded text-sm">Ctrl</kbd> + <kbd class="px-2 py-1 bg-gray-800 text-white rounded text-sm">K</kbd> em qualquer tela</li>
                                <li>Digite o termo de busca (nome, BI, código, etc.)</li>
                                <li>Os resultados aparecem em tempo real</li>
                                <li>Clique no resultado desejado para acessar</li>
                            </ol>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">O Que Pode Buscar</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-900 mb-2">
                                        <i class="bi bi-people text-emerald-600 mr-2"></i>Pacientes
                                    </h4>
                                    <p class="text-sm text-gray-600">Por nome, BI, código MS-XXXX ou telefone</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-900 mb-2">
                                        <i class="bi bi-clipboard2-pulse text-blue-600 mr-2"></i>Exames
                                    </h4>
                                    <p class="text-sm text-gray-600">Por nome do exame ou código</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-900 mb-2">
                                        <i class="bi bi-capsule text-purple-600 mr-2"></i>Medicamentos
                                    </h4>
                                    <p class="text-sm text-gray-600">Por nome comercial ou genérico</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-gray-900 mb-2">
                                        <i class="bi bi-receipt text-yellow-600 mr-2"></i>Faturas
                                    </h4>
                                    <p class="text-sm text-gray-600">Por número da fatura ou paciente</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded">
                            <h4 class="font-semibold text-indigo-800 mb-2">Atalhos de Teclado</h4>
                            <ul class="text-indigo-700 text-sm space-y-1">
                                <li><kbd class="px-2 py-1 bg-indigo-800 text-white rounded text-xs">Ctrl+K</kbd> - Abrir busca global</li>
                                <li><kbd class="px-2 py-1 bg-indigo-800 text-white rounded text-xs">Esc</kbd> - Fechar busca</li>
                                <li><kbd class="px-2 py-1 bg-indigo-800 text-white rounded text-xs">↑↓</kbd> - Navegar nos resultados</li>
                                <li><kbd class="px-2 py-1 bg-indigo-800 text-white rounded text-xs">Enter</kbd> - Selecionar resultado</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONFIGURAÇÕES -->
            <div class="doc-section" id="section-config">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-gear text-gray-600 mr-2"></i>
                        Configurações do Sistema
                    </h2>
                    
                    <div class="space-y-6">
                        <p class="text-gray-700">
                            Acesse <strong>Configurações</strong> no menu principal para personalizar o sistema conforme as necessidades da clínica.
                        </p>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Configurações Disponíveis</h3>
                            
                            <div class="space-y-4">
                                <div class="border-l-4 border-emerald-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Geral</h4>
                                    <p class="text-sm text-gray-600">Nome da clínica, NUIT, endereço, telefone e e-mail</p>
                                </div>
                                
                                <div class="border-l-4 border-blue-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Financeiro</h4>
                                    <p class="text-sm text-gray-600">Métodos de pagamento, prefixo de faturas, descontos e taxas</p>
                                </div>
                                
                                <div class="border-l-4 border-purple-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Consultas</h4>
                                    <p class="text-sm text-gray-600">Duração, horários de funcionamento, lembretes por SMS</p>
                                </div>
                                
                                <div class="border-l-4 border-red-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Laboratório</h4>
                                    <p class="text-sm text-gray-600">Prazo de resultados, validação técnica, impressão automática</p>
                                </div>
                                
                                <div class="border-l-4 border-yellow-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Farmácia</h4>
                                    <p class="text-sm text-gray-600">Alertas de estoque, validade, prescrição obrigatória</p>
                                </div>
                                
                                <div class="border-l-4 border-gray-500 pl-4">
                                    <h4 class="font-semibold text-gray-900">Sistema</h4>
                                    <p class="text-sm text-gray-600">Timeout de sessão, backup automático, log de auditoria</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div class="doc-section" id="section-faq">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-question-circle text-blue-600 mr-2"></i>
                        Perguntas Frequentes (FAQ)
                    </h2>
                    
                    <div class="space-y-4">
                        <!-- FAQ Item -->
                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">Como alterar minha senha?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Entre em contato com o administrador do sistema ou suporte técnico para solicitar a alteração de senha por segurança.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">O sistema funciona offline?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Não. O sistema requer conexão à rede local onde o servidor está hospedado. Garanta que o Apache e MySQL estejam rodando no XAMPP.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">Como imprimir resultados de exames?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">No módulo Laboratório, clique no ícone de impressora ao lado do exame validado. O sistema gera automaticamente um PDF profissional com o logotipo da clínica.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">Posso cancelar um agendamento?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Sim. Na recepção, localize o agendamento e clique em "Cancelar". O horário fica disponível para outro paciente.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">Como adicionar um novo usuário ao sistema?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Apenas administradores podem adicionar usuários. Acesse o módulo de Administração → Usuários → Novo Usuário e preencha os dados necessários.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">O que fazer se um paciente não tem BI?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Você pode usar um identificador temporário como "TEMP" seguido de números. No entanto, recomendamos obter o BI o mais rápido possível e atualizar o cadastro.</p>
                            </div>
                        </div>

                        <div class="border border-gray-200 rounded-lg">
                            <button onclick="toggleAccordion(this)" class="w-full flex justify-between items-center p-4 text-left hover:bg-gray-50 transition">
                                <span class="font-semibold text-gray-900">Como fazer backup dos dados?</span>
                                <i class="bi bi-chevron-down text-gray-600"></i>
                            </button>
                            <div class="accordion-content px-4 pb-4">
                                <p class="text-gray-700">Backups automáticos podem ser configurados em Configurações → Sistema. Para backup manual, use phpMyAdmin para exportar o banco de dados "clinica_ms".</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUPORTE -->
            <div class="doc-section" id="section-suporte">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <i class="bi bi-headset text-green-600 mr-2"></i>
                        Suporte Técnico
                    </h2>
                    
                    <div class="space-y-6">
                        <p class="text-gray-700">
                            Precisa de ajuda? Entre em contato com nossa equipe de suporte:
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gradient-to-br from-emerald-50 to-green-50 p-6 rounded-lg border border-emerald-200">
                                <h3 class="font-semibold text-gray-900 mb-4">Suporte Técnico</h3>
                                <div class="space-y-2">
                                    <p class="text-gray-800"><strong>Domingos João Mangação</strong></p>
                                    <p class="text-gray-700 flex items-center">
                                        <i class="bi bi-envelope mr-2"></i>
                                        domingos.mangacao@maissaude.co.mz
                                    </p>
                                    <p class="text-gray-700 flex items-center">
                                        <i class="bi bi-telephone mr-2"></i>
                                        +258 84 000 0000
                                    </p>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200">
                                <h3 class="font-semibold text-gray-900 mb-4">Administração</h3>
                                <div class="space-y-2">
                                    <p class="text-gray-800"><strong>Heloísa da Aldina Manuel</strong></p>
                                    <p class="text-gray-700 flex items-center">
                                        <i class="bi bi-envelope mr-2"></i>
                                        heloisa.aldina@maissaude.co.mz
                                    </p>
                                    <p class="text-gray-700">Diretora Geral</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                            <h4 class="font-semibold text-yellow-800 mb-2">Horário de Atendimento</h4>
                            <p class="text-yellow-700 text-sm">Segunda a Sexta: 08:00 - 18:00</p>
                            <p class="text-yellow-700 text-sm">Sábado: 08:00 - 13:00</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">Informações do Sistema</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-700"><strong>Versão:</strong> 1.0.0</p>
                                <p class="text-sm text-gray-700"><strong>Última Atualização:</strong> Dezembro 2024</p>
                                <p class="text-sm text-gray-700"><strong>Desenvolvido por:</strong> Equipe Integrada Mais Saúde</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.doc-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active from all nav items
    document.querySelectorAll('.doc-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById('section-' + sectionName).classList.add('active');
    
    // Highlight active nav item
    event.target.classList.add('active');
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleAccordion(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('i');
    
    content.classList.toggle('active');
    
    if (content.classList.contains('active')) {
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
    } else {
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
