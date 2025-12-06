<!-- Busca Global Estilo Spotlight -->
<div id="globalSearchModal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] hidden items-center justify-center p-4 backdrop-blur-sm" onclick="if(event.target === this) closeGlobalSearch()">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[80vh] flex flex-col transform transition-all duration-300 scale-95 opacity-0" id="searchModalContent">
        
        <!-- Header com Input -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <i class="bi bi-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xl"></i>
                <input 
                    type="text" 
                    id="globalSearchInput" 
                    placeholder="Buscar pacientes, exames, medicamentos, faturas... (Ctrl+K)"
                    class="w-full pl-12 pr-12 py-4 text-lg rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-blue-500 focus:ring-4 focus:ring-blue-200 dark:focus:ring-blue-900 transition-all"
                    autocomplete="off"
                    oninput="performGlobalSearch(this.value)"
                />
                <button onclick="closeGlobalSearch()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs font-mono">ESC</kbd>
                </button>
            </div>
            
            <!-- Filtros Rápidos -->
            <div class="flex gap-2 mt-4 flex-wrap" id="searchFilters">
                <button onclick="setSearchFilter('all')" data-filter="all" class="filter-btn active px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-blue-500 text-white">
                    <i class="bi bi-grid-3x3-gap-fill mr-1"></i>Todos
                </button>
                <button onclick="setSearchFilter('patients')" data-filter="patients" class="filter-btn px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i class="bi bi-person-fill mr-1"></i>Pacientes
                </button>
                <button onclick="setSearchFilter('exams')" data-filter="exams" class="filter-btn px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i class="bi bi-flask mr-1"></i>Exames
                </button>
                <button onclick="setSearchFilter('medications')" data-filter="medications" class="filter-btn px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i class="bi bi-capsule mr-1"></i>Medicamentos
                </button>
                <button onclick="setSearchFilter('invoices')" data-filter="invoices" class="filter-btn px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                    <i class="bi bi-receipt mr-1"></i>Faturas
                </button>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="searchLoading" class="hidden p-4 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Buscando...</p>
        </div>

        <!-- Resultados -->
        <div class="flex-1 overflow-y-auto" id="globalSearchResults">
            <!-- Histórico de Buscas Recentes -->
            <div id="recentSearches" class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <i class="bi bi-clock-history mr-2"></i>Buscas Recentes
                    </h3>
                    <button onclick="clearRecentSearches()" class="text-xs text-red-500 hover:text-red-600 transition-colors">
                        Limpar
                    </button>
                </div>
                <div id="recentSearchList" class="space-y-2">
                    <!-- Preenchido por JS -->
                </div>
            </div>

            <!-- Resultados da Busca -->
            <div id="searchResultsContainer" class="hidden">
                <!-- Pacientes -->
                <div id="patientsResults" class="hidden">
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-y border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            <i class="bi bi-person-fill mr-2"></i>Pacientes
                        </h3>
                    </div>
                    <div id="patientsResultsList" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Preenchido por JS -->
                    </div>
                </div>

                <!-- Exames -->
                <div id="examsResults" class="hidden">
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-y border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            <i class="bi bi-flask mr-2"></i>Exames
                        </h3>
                    </div>
                    <div id="examsResultsList" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Preenchido por JS -->
                    </div>
                </div>

                <!-- Medicamentos -->
                <div id="medicationsResults" class="hidden">
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-y border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            <i class="bi bi-capsule mr-2"></i>Medicamentos
                        </h3>
                    </div>
                    <div id="medicationsResultsList" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Preenchido por JS -->
                    </div>
                </div>

                <!-- Faturas -->
                <div id="invoicesResults" class="hidden">
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-y border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            <i class="bi bi-receipt mr-2"></i>Faturas
                        </h3>
                    </div>
                    <div id="invoicesResultsList" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Preenchido por JS -->
                    </div>
                </div>

                <!-- Sem Resultados -->
                <div id="noResults" class="hidden p-12 text-center">
                    <i class="bi bi-search text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">Nenhum resultado encontrado</h3>
                    <p class="text-gray-500 dark:text-gray-400">Tente buscar com outros termos</p>
                </div>
            </div>
        </div>

        <!-- Footer com Atalhos -->
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-4">
                    <span><kbd class="kbd">↑↓</kbd> Navegar</span>
                    <span><kbd class="kbd">Enter</kbd> Selecionar</span>
                    <span><kbd class="kbd">ESC</kbd> Fechar</span>
                </div>
                <span class="text-gray-400">Dica: Use filtros para refinar a busca</span>
            </div>
        </div>
    </div>
</div>

<style>
.kbd {
    display: inline-block;
    padding: 2px 6px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    font-weight: 600;
}
.dark .kbd {
    background: #374151;
    border-color: #4b5563;
}
.filter-btn.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}
</style>

<script>
let searchTimeout;
let currentFilter = 'all';
let selectedIndex = -1;

// Abrir modal com Ctrl+K ou Cmd+K
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openGlobalSearch();
    } else if (e.key === 'Escape' && document.getElementById('globalSearchModal').classList.contains('flex')) {
        closeGlobalSearch();
    }
});

function openGlobalSearch() {
    const modal = document.getElementById('globalSearchModal');
    const content = document.getElementById('searchModalContent');
    const input = document.getElementById('globalSearchInput');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
        input.focus();
    }, 10);
    
    loadRecentSearches();
}

function closeGlobalSearch() {
    const modal = document.getElementById('globalSearchModal');
    const content = document.getElementById('searchModalContent');
    const input = document.getElementById('globalSearchInput');
    
    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        input.value = '';
        document.getElementById('recentSearches').classList.remove('hidden');
        document.getElementById('searchResultsContainer').classList.add('hidden');
    }, 300);
}

function setSearchFilter(filter) {
    currentFilter = filter;
    
    // Atualizar botões
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-500', 'text-white');
        btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    });
    
    const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
    activeBtn.classList.add('active', 'bg-blue-500', 'text-white');
    activeBtn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
    
    // Refazer busca se houver texto
    const searchText = document.getElementById('globalSearchInput').value;
    if (searchText.length >= 2) {
        performGlobalSearch(searchText);
    }
}

function performGlobalSearch(query) {
    clearTimeout(searchTimeout);
    
    if (query.length < 2) {
        document.getElementById('recentSearches').classList.remove('hidden');
        document.getElementById('searchResultsContainer').classList.add('hidden');
        return;
    }
    
    document.getElementById('recentSearches').classList.add('hidden');
    document.getElementById('searchLoading').classList.remove('hidden');
    
    searchTimeout = setTimeout(() => {
        fetch('/MSLDA/api/global_search.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                query: query,
                filter: currentFilter
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('searchLoading').classList.add('hidden');
            displaySearchResults(data);
            saveToRecentSearches(query);
        })
        .catch(error => {
            console.error('Erro na busca:', error);
            document.getElementById('searchLoading').classList.add('hidden');
        });
    }, 300); // Debounce de 300ms
}

function displaySearchResults(data) {
    document.getElementById('searchResultsContainer').classList.remove('hidden');
    
    // Limpar resultados anteriores
    document.getElementById('patientsResultsList').innerHTML = '';
    document.getElementById('examsResultsList').innerHTML = '';
    document.getElementById('medicationsResultsList').innerHTML = '';
    document.getElementById('invoicesResultsList').innerHTML = '';
    
    // Ocultar todas as seções
    document.getElementById('patientsResults').classList.add('hidden');
    document.getElementById('examsResults').classList.add('hidden');
    document.getElementById('medicationsResults').classList.add('hidden');
    document.getElementById('invoicesResults').classList.add('hidden');
    document.getElementById('noResults').classList.add('hidden');
    
    let hasResults = false;
    
    // Pacientes
    if (data.patients && data.patients.length > 0) {
        hasResults = true;
        document.getElementById('patientsResults').classList.remove('hidden');
        data.patients.forEach(patient => {
            const item = createPatientResultItem(patient);
            document.getElementById('patientsResultsList').appendChild(item);
        });
    }
    
    // Exames
    if (data.exams && data.exams.length > 0) {
        hasResults = true;
        document.getElementById('examsResults').classList.remove('hidden');
        data.exams.forEach(exam => {
            const item = createExamResultItem(exam);
            document.getElementById('examsResultsList').appendChild(item);
        });
    }
    
    // Medicamentos
    if (data.medications && data.medications.length > 0) {
        hasResults = true;
        document.getElementById('medicationsResults').classList.remove('hidden');
        data.medications.forEach(med => {
            const item = createMedicationResultItem(med);
            document.getElementById('medicationsResultsList').appendChild(item);
        });
    }
    
    // Faturas
    if (data.invoices && data.invoices.length > 0) {
        hasResults = true;
        document.getElementById('invoicesResults').classList.remove('hidden');
        data.invoices.forEach(invoice => {
            const item = createInvoiceResultItem(invoice);
            document.getElementById('invoicesResultsList').appendChild(item);
        });
    }
    
    if (!hasResults) {
        document.getElementById('noResults').classList.remove('hidden');
    }
}

function createPatientResultItem(patient) {
    const div = document.createElement('div');
    div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors';
    div.onclick = () => window.location.href = `/MSLDA/modules/recepcao/index.php?patient_id=${patient.id}`;
    
    div.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="bi bi-person-fill text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-white">${patient.name}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        ${patient.codigo} • ${patient.cpf || 'Sem BI'}
                    </p>
                </div>
            </div>
            <i class="bi bi-arrow-right text-gray-400"></i>
        </div>
    `;
    
    return div;
}

function createExamResultItem(exam) {
    const div = document.createElement('div');
    div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors';
    div.onclick = () => window.location.href = `/MSLDA/modules/laboratorio/index.php?codigo_paciente=${exam.patient_codigo}`;
    
    div.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                    <i class="bi bi-flask text-purple-600 dark:text-purple-400"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-white">${exam.exam_name}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Paciente: ${exam.patient_name} • ${exam.scheduled_at ? new Date(exam.scheduled_at).toLocaleDateString('pt-BR') : 'Data não disponível'}
                    </p>
                </div>
            </div>
            <i class="bi bi-arrow-right text-gray-400"></i>
        </div>
    `;
    
    return div;
}

function createMedicationResultItem(med) {
    const div = document.createElement('div');
    div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors';
    div.onclick = () => window.location.href = `/MSLDA/modules/farmacia/index.php?medication_id=${med.id}`;
    
    const stockBadge = med.stock_quantity > 0 
        ? `<span class="text-xs px-2 py-1 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded-full">Em estoque: ${med.stock_quantity}</span>`
        : `<span class="text-xs px-2 py-1 bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 rounded-full">Sem estoque</span>`;
    
    div.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="bi bi-capsule text-green-600 dark:text-green-400"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-white">${med.name}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        ${med.description || 'Sem descrição'} • ${stockBadge}
                    </p>
                </div>
            </div>
            <i class="bi bi-arrow-right text-gray-400"></i>
        </div>
    `;
    
    return div;
}

function createInvoiceResultItem(invoice) {
    const div = document.createElement('div');
    div.className = 'p-4 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors';
    div.onclick = () => window.location.href = `/MSLDA/modules/financeiro/ver_fatura.php?id=${invoice.id}`;
    
    const statusColors = {
        'pendente': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
        'pago': 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'vencido': 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        'parcialmente_pago': 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
    };
    
    div.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                    <i class="bi bi-receipt text-orange-600 dark:text-orange-400"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-white">${invoice.invoice_number}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        ${invoice.patient_name} • 
                        <span class="text-xs px-2 py-1 rounded-full ${statusColors[invoice.status] || statusColors.pendente}">
                            ${invoice.status}
                        </span> • 
                        ${parseFloat(invoice.total).toLocaleString('pt-BR', {style: 'currency', currency: 'MZN'})}
                    </p>
                </div>
            </div>
            <i class="bi bi-arrow-right text-gray-400"></i>
        </div>
    `;
    
    return div;
}

// Histórico de Buscas Recentes
function loadRecentSearches() {
    const recent = JSON.parse(localStorage.getItem('recentSearches') || '[]');
    const container = document.getElementById('recentSearchList');
    
    if (recent.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Nenhuma busca recente</p>';
        return;
    }
    
    container.innerHTML = recent.map(term => `
        <div class="flex items-center justify-between p-3 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer transition-colors" onclick="document.getElementById('globalSearchInput').value='${term}'; performGlobalSearch('${term}');">
            <div class="flex items-center gap-3">
                <i class="bi bi-clock-history text-gray-400"></i>
                <span class="text-gray-700 dark:text-gray-300">${term}</span>
            </div>
            <button onclick="event.stopPropagation(); removeRecentSearch('${term}')" class="text-gray-400 hover:text-red-500 transition-colors">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    `).join('');
}

function saveToRecentSearches(term) {
    let recent = JSON.parse(localStorage.getItem('recentSearches') || '[]');
    recent = recent.filter(t => t !== term); // Remove duplicatas
    recent.unshift(term); // Adiciona no início
    recent = recent.slice(0, 5); // Mantém apenas os 5 mais recentes
    localStorage.setItem('recentSearches', JSON.stringify(recent));
}

function removeRecentSearch(term) {
    let recent = JSON.parse(localStorage.getItem('recentSearches') || '[]');
    recent = recent.filter(t => t !== term);
    localStorage.setItem('recentSearches', JSON.stringify(recent));
    loadRecentSearches();
}

function clearRecentSearches() {
    localStorage.removeItem('recentSearches');
    loadRecentSearches();
}
</script>
