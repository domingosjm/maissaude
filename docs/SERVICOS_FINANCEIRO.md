# 🎯 Módulo Financeiro - Sistema de Serviços

## ✅ Funcionalidades Implementadas

### 📝 Cadastro de Serviços
- **Criar Novo Serviço**
  - Nome do serviço (obrigatório)
  - Código/SKU único (opcional)
  - Categoria (Consulta, Exame, Procedimento, Medicamento, Outros)
  - Descrição detalhada
  - Preço de venda (obrigatório)
  - Custo (opcional)
  - Taxa de imposto personalizada (padrão: 16%)
  - Cálculo automático de margem de lucro

- **Validações**
  - Nome obrigatório
  - Código único (não pode duplicar)
  - Preço não pode ser negativo
  - Feedback visual da margem de lucro (verde > 30%, amarelo > 15%, vermelho < 15%)

### ✏️ Edição de Serviços
- Editar todos os campos do serviço
- Manter histórico de uso (não permite exclusão se usado em faturas)
- Modal pré-preenchido com dados atuais
- Recálculo automático de margem ao editar

### 📋 Duplicação de Serviços
- Copiar serviço existente com um clique
- Nome automático com sufixo "(Cópia)"
- Código limpo (para evitar duplicação)
- Útil para criar variações de serviços semelhantes

### 🔍 Busca e Filtros
- Campo de busca em tempo real
- Filtra por nome ou descrição
- Interface responsiva

### 🔄 Ativar/Desativar Serviços
- Toggle rápido de status
- Serviços inativos não aparecem em novos faturamentos
- Mantém dados históricos

### 🗑️ Exclusão de Serviços
- Verifica uso em faturas antes de excluir
- Proteção contra perda de dados
- Sugere desativação se serviço estiver em uso

### 📊 Visualização
- Grid organizado por categoria
- Cards coloridos por tipo de serviço
- Ícones distintivos para cada categoria
- Exibição de preço em destaque
- Botões de ação sempre visíveis

### 🎨 Interface
- Design moderno com Tailwind CSS
- Modo escuro completo
- Animações suaves (hover-lift)
- Responsivo (mobile, tablet, desktop)
- Feedback visual de ações

## 🛠️ Estrutura do Banco de Dados

```sql
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(50) NULL UNIQUE,           -- Novo campo
    category ENUM('consulta','exame','procedimento','medicamento','outros') NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    cost DECIMAL(10,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 16.00,    -- Novo campo
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 📌 Categorias Disponíveis

| Categoria | Ícone | Cor | Exemplos |
|-----------|-------|-----|----------|
| **Consultas** | 🏥 | Azul | Consulta Geral, Consulta Especializada |
| **Exames** | 📋 | Roxo | Hemograma, Glicose, Raio-X |
| **Procedimentos** | 🩹 | Verde | Curativo, Sutura, Cirurgia |
| **Medicamentos** | 💊 | Vermelho | Antibióticos, Analgésicos |
| **Outros** | ⚡ | Cinza | Materiais, Taxas |

## 🚀 Como Usar

### Criar Novo Serviço
1. Clique em "Novo Serviço"
2. Preencha o formulário:
   - Nome: Ex: "Hemograma Completo"
   - Código: Ex: "EX001" (opcional)
   - Categoria: Selecione "Exame"
   - Preço: Ex: 150.00
   - Custo: Ex: 80.00 (margem calculada automaticamente)
   - Taxa: 16% (IVA padrão)
3. Clique em "Salvar Serviço"

### Editar Serviço
1. Localize o serviço no catálogo
2. Clique no ícone de lápis (✏️)
3. Modifique os campos desejados
4. Clique em "Salvar Serviço"

### Duplicar Serviço
1. Localize o serviço que deseja duplicar
2. Clique no ícone de cópia (📄)
3. Ajuste nome e código
4. Salve o novo serviço

### Buscar Serviços
1. Digite no campo de busca
2. Filtragem instantânea por nome/descrição

### Ativar/Desativar
1. Clique no ícone de toggle (🔄)
2. Confirme a ação

### Excluir Serviço
1. Clique no ícone de lixeira (🗑️)
2. Sistema verifica se está em uso
3. Confirme exclusão (ou desative se em uso)

## 💡 Dicas de Uso

### Organização
- Use códigos padronizados: EX001, CON001, PROC001
- Mantenha descrições claras e completas
- Atualize custos regularmente para margem correta

### Preços
- Defina custo para acompanhar rentabilidade
- Margem ideal: > 30% (verde)
- Margem aceitável: 15-30% (amarelo)
- Revisar se < 15% (vermelho)

### Categorização
- Categorize corretamente para relatórios precisos
- Use "Outros" apenas quando necessário
- Facilita busca e análise financeira

## 🔒 Segurança
- Validação de dados no backend
- Proteção contra SQL injection (prepared statements)
- Código único garante integridade
- Verificação de uso antes de exclusão

## 📱 Responsividade
- Mobile: Lista vertical com cards completos
- Tablet: Grid 2 colunas
- Desktop: Grid 3 colunas
- Busca sempre visível

## 🎨 Personalização
- Cores por categoria facilita identificação
- Ícones intuitivos para ações rápidas
- Feedback visual imediato
- Animações sutis para melhor UX

## 📈 Próximas Melhorias (Sugestões)
- [ ] Importação em massa (CSV/Excel)
- [ ] Exportação de catálogo
- [ ] Histórico de alterações de preço
- [ ] Serviços compostos (pacotes)
- [ ] Controle de estoque (para medicamentos)
- [ ] Relatório de rentabilidade por serviço
- [ ] Tags personalizadas
- [ ] Descontos por volume
