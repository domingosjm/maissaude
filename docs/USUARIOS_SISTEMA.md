# 👥 Sistema de Usuários e Permissões - MSLDA

## ✅ Configuração Concluída

O sistema de usuários com permissões granulares por módulo foi implementado com sucesso!

---

## 📋 Usuários Criados

| Usuário | Senha | Nome Completo | Cargo | Departamento |
|---------|-------|---------------|-------|--------------|
| `heloisa.aldina` | senha123 | Heloísa da Aldina | Diretora Geral | Direção Geral |
| `zulfa.nhanssen` | senha123 | Zulfa Boavida Nhanssen | Diretora Administrativa | Administração e Financeiro |
| `claudio.diogo` | senha123 | Cláudio Celestino Diogo | Diretor Técnico | Laboratório |
| `malber.muchiguel` | senha123 | Malber Muchiguel | Diretora Técnica | Farmácia |
| `laurinda.canaogai` | senha123 | Laurinda Rosa Canaogai | Diretora Técnica | Psicologia |
| `elizeth.joao` | senha123 | Elizeth de Fidalgo João | Analista Clínica | Laboratório |
| `inarah.madvgi` | senha123 | Inarah Madvgi | Analista Clínica | Laboratório |
| `elias.chimue` | senha123 | Elias Filipe Chimué | Farmacêutico | Farmácia |
| `tania.belo` | senha123 | Tânia Olívia Leão Belo | Psicóloga | Psicologia |
| `maria.rosario` | senha123 | Maria Luisa de Rosário | Psiquiatra | Psiquiatria |
| `domingos.mangacao` | senha123 | Domingos João Mangação | Técnico de TI | TI |
| `juliana.feniasse` | senha123 | Juliana Mateus Feniasse | Recepcionista | Recepção |
| `evanilda.ziba` | senha123 | Evanilda Arnaldo Ziba Tomo | Médica | Medicina Geral |
| `ivan.macdonald` | senha123 | Ivan Mac Donald | Enfermeiro | Enfermagem |

⚠️ **IMPORTANTE**: Todos os usuários foram criados com a senha padrão `senha123`. Recomenda-se alterar as senhas no primeiro acesso!

---

## 🔐 Perfis e Permissões

### 1. Diretor Geral (heloisa.aldina)
**Acesso Total**: ✅ Ver | ✅ Criar | ✅ Editar | ✅ Excluir

- Dashboard
- Recepção
- Consultas
- Internação
- Enfermagem
- Laboratório
- Farmácia
- Psicologia
- Financeiro
- Relatórios
- Configurações
- Usuários

---

### 2. Diretor Administrativo (zulfa.nhanssen)
**Gestão Administrativa e Financeira**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Recepção | ✅ | ✅ | ✅ | ✅ |
| Financeiro | ✅ | ✅ | ✅ | ✅ |
| Relatórios | ✅ | ✅ | ✅ | ✅ |
| Usuários | ✅ | ✅ | ✅ | ✅ |
| Consultas | ✅ | ❌ | ❌ | ❌ |
| Internação | ✅ | ❌ | ❌ | ❌ |
| Enfermagem | ✅ | ❌ | ❌ | ❌ |
| Laboratório | ✅ | ❌ | ❌ | ❌ |
| Farmácia | ✅ | ❌ | ❌ | ❌ |
| Psicologia | ✅ | ❌ | ❌ | ❌ |

---

### 3. Diretor Técnico - Laboratório (claudio.diogo)
**Gestão Técnica do Laboratório**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Laboratório | ✅ | ✅ | ✅ | ✅ |
| Relatórios | ✅ | ✅ | ✅ | ✅ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Consultas | ✅ | ❌ | ❌ | ❌ |

---

### 4. Médica (evanilda.ziba)
**Atendimento Médico e Consultas**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Consultas | ✅ | ✅ | ✅ | ❌ |
| Internação | ✅ | ✅ | ✅ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Laboratório | ✅ | ❌ | ❌ | ❌ |
| Farmácia | ✅ | ❌ | ❌ | ❌ |
| Relatórios | ✅ | ❌ | ❌ | ❌ |

---

### 5. Enfermeiro (ivan.macdonald)
**Procedimentos de Enfermagem**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Enfermagem | ✅ | ✅ | ✅ | ❌ |
| Internação | ✅ | ✅ | ✅ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Consultas | ✅ | ❌ | ❌ | ❌ |
| Farmácia | ✅ | ❌ | ❌ | ❌ |

---

### 6. Farmacêuticos (malber.muchiguel, elias.chimue)
**Gestão de Farmácia e Medicamentos**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Farmácia | ✅ | ✅ | ✅ | ✅ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Consultas | ✅ | ❌ | ❌ | ❌ |

---

### 7. Psicóloga (laurinda.canaogai, tania.belo)
**Atendimento Psicológico**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Psicologia | ✅ | ✅ | ✅ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Consultas | ✅ | ❌ | ❌ | ❌ |

---

### 8. Psiquiatra (maria.rosario)
**Atendimento Psiquiátrico**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Psicologia | ✅ | ✅ | ✅ | ❌ |
| Consultas | ✅ | ✅ | ✅ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |
| Farmácia | ✅ | ❌ | ❌ | ❌ |

---

### 9. Técnico de Laboratório (claudio.diogo)
**Gestão de Exames**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Laboratório | ✅ | ✅ | ✅ | ✅ |
| Dashboard | ✅ | ❌ | ❌ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |

---

### 10. Analistas Clínicas (elizeth.joao, inarah.madvgi)
**Análise de Exames**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Laboratório | ✅ | ✅ | ✅ | ❌ |
| Dashboard | ✅ | ❌ | ❌ | ❌ |
| Recepção | ✅ | ❌ | ❌ | ❌ |

---

### 11. Recepcionista (juliana.feniasse)
**Recepção e Agendamentos**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Dashboard | ✅ | ✅ | ✅ | ❌ |
| Recepção | ✅ | ✅ | ✅ | ❌ |
| Financeiro | ✅ | ✅ | ✅ | ❌ |

---

### 12. Técnico de TI (domingos.mangacao)
**Suporte Técnico e Configurações**

| Módulo | Ver | Criar | Editar | Excluir |
|--------|-----|-------|--------|---------|
| Configurações | ✅ | ✅ | ✅ | ✅ |
| Usuários | ✅ | ✅ | ✅ | ✅ |
| Todos os outros | ✅ | ❌ | ❌ | ❌ |

---

## 🗄️ Estrutura do Banco de Dados

### Tabela: `user_roles`
Armazena os perfis/funções dos usuários

```sql
id, name, description, created_at
```

### Tabela: `system_modules`
Lista todos os módulos do sistema

```sql
id, name, code, description, icon, url, display_order, active, created_at
```

### Tabela: `role_permissions`
Define as permissões de cada perfil para cada módulo

```sql
id, role_id, module_id, can_view, can_create, can_edit, can_delete, created_at
```

### Tabela: `system_users`
Dados dos usuários do sistema

```sql
id, username, password, role_id, full_name, email, phone, 
department, status, last_login, created_at, updated_at
```

---

## 🔧 Próximos Passos

### 1. Atualizar Login (index.php)
Modificar para usar a tabela `system_users` em vez de `users`

### 2. Criar Middleware de Permissões
Adicionar verificação de permissões em cada módulo:

```php
function hasPermission($user_id, $module_code, $action) {
    // $action: 'view', 'create', 'edit', 'delete'
    global $mysqli;
    
    $query = "SELECT rp.can_$action 
              FROM system_users su
              JOIN role_permissions rp ON su.role_id = rp.role_id
              JOIN system_modules sm ON rp.module_id = sm.id
              WHERE su.id = ? AND sm.code = ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('is', $user_id, $module_code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result && $result["can_$action"] == 1;
}
```

### 3. Criar Página de Gestão de Usuários
Interface para admin gerenciar usuários e permissões

### 4. Implementar Logs de Auditoria
Registrar todas as ações dos usuários para rastreabilidade

---

## 📞 Suporte

Para alteração de permissões ou criação de novos usuários, entre em contato com:
- **Heloísa da Aldina** (Diretora Geral)
- **Domingos João Mangação** (Técnico de TI)
