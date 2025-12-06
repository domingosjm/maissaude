# 📧 Credenciais de Acesso - MSLDA

## Login do Sistema

**URL de Acesso**: http://localhost/MSLDA/index.php

**Método de Login**: Email + Senha

---

## 👥 Usuários e Emails

| Nome Completo | Email | Senha | Cargo | Departamento |
|---------------|-------|-------|-------|--------------|
| Heloísa da Aldina | `heloisa.aldina@maissaude.co.mz` | senha123 | Diretora Geral | Direção Geral |
| Zulfa Boavida Nhanssen | `zulfa.nhanssen@maissaude.co.mz` | senha123 | Diretora Administrativa | Administração e Financeiro |
| Cláudio Celestino Diogo | `claudio.diogo@maissaude.co.mz` | senha123 | Diretor Técnico | Laboratório |
| Malber Muchiguel | `malber.muchiguel@maissaude.co.mz` | senha123 | Diretora Técnica | Farmácia |
| Laurinda Rosa Canaogai | `laurinda.canaogai@maissaude.co.mz` | senha123 | Diretora Técnica | Psicologia |
| Elizeth de Fidalgo João | `elizeth.joao@maissaude.co.mz` | senha123 | Analista Clínica | Laboratório |
| Inarah Madvgi | `inarah.madvgi@maissaude.co.mz` | senha123 | Analista Clínica | Laboratório |
| Elias Filipe Chimué | `elias.chimue@maissaude.co.mz` | senha123 | Farmacêutico | Farmácia |
| Tânia Olívia Leão Belo | `tania.belo@maissaude.co.mz` | senha123 | Psicóloga | Psicologia |
| Maria Luisa de Rosário | `maria.rosario@maissaude.co.mz` | senha123 | Psiquiatra | Psiquiatria |
| Domingos João Mangação | `domingos.mangacao@maissaude.co.mz` | senha123 | Técnico de TI | TI |
| Juliana Mateus Feniasse | `juliana.feniasse@maissaude.co.mz` | senha123 | Recepcionista | Recepção |
| Evanilda Arnaldo Ziba Tomo | `evanilda.ziba@maissaude.co.mz` | senha123 | Médica | Medicina Geral |
| Ivan Mac Donald | `ivan.macdonald@maissaude.co.mz` | senha123 | Enfermeiro | Enfermagem |

---

## 🔐 Acesso Administrativo

### Super Admin
- **Email**: `admin@clinica.com`
- **Senha**: (senha original do sistema)
- **Acesso**: Total

---

## 📋 Exemplo de Login

1. Acesse: http://localhost/MSLDA/index.php
2. Digite o email: `heloisa.aldina@maissaude.co.mz`
3. Digite a senha: `senha123`
4. Clique em "Entrar"

---

## 🔒 Segurança

⚠️ **IMPORTANTE**: 
- Todos os usuários foram criados com a senha padrão `senha123`
- **ALTERE AS SENHAS** após o primeiro acesso!
- As senhas estão criptografadas com bcrypt no banco de dados
- Recomenda-se usar senhas fortes com:
  - Mínimo 8 caracteres
  - Letras maiúsculas e minúsculas
  - Números
  - Caracteres especiais

---

## 🛠️ Gerenciamento de Usuários

### Para adicionar novos usuários:

```sql
INSERT INTO users (name, email, password) VALUES
('Nome Completo', 'email@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO system_users (username, password, role_id, full_name, email, department, status) VALUES
('username', MD5('senha123'), ROLE_ID, 'Nome Completo', 'email@maissaude.co.mz', 'Departamento', 'ativo');
```

### Para alterar senha:

```sql
-- Senha: novasenha123
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE email = 'email@maissaude.co.mz';
```

---

## 📞 Suporte

Em caso de problemas com acesso, contacte:
- **Domingos João Mangação** (Técnico de TI)
- **Email**: domingos.mangacao@maissaude.co.mz
