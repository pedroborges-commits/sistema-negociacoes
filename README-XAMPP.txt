SISTEMA DE NEGOCIAÇÕES
=======================

Sistema web para registro, acompanhamento e controle de atendimentos
realizados com associados, incluindo registro posterior de pagamentos,
relatórios e auditoria das alterações.


1. TECNOLOGIAS
==============

Backend:
- PHP
- PDO
- MySQL/MariaDB

Frontend:
- HTML
- CSS
- JavaScript

Ambiente local:
- XAMPP
- Apache
- MySQL

Banco de dados:
- negociacoes


2. REQUISITOS
=============

Para executar o sistema localmente:

- Windows
- XAMPP instalado
- Apache ativo
- MySQL ativo
- Navegador web


3. INSTALAÇÃO NO XAMPP
======================

O projeto deve estar dentro da pasta htdocs do XAMPP.

Caminho utilizado neste projeto:

C:\xampp\htdocs\sistema-negociacoes


4. CONFIGURAÇÃO DO BANCO
========================

O banco utilizado pelo sistema possui o nome:

negociacoes

No XAMPP:

1. Abra o XAMPP Control Panel.
2. Inicie o Apache.
3. Inicie o MySQL.
4. Acesse o phpMyAdmin.

Endereço padrão:

http://localhost/phpmyadmin


Para uma instalação nova:

1. Abra o arquivo:

database.sql

2. Execute o script no MySQL/phpMyAdmin.

O script cria:

- Banco negociacoes
- Tabela perfis
- Tabela usuarios
- Tabela clientes
- Tabela atendimentos
- Tabela negociacoes
- Tabela auditoria

Também cria os perfis e usuários iniciais definidos no script.


5. CONFIGURAÇÃO DA CONEXÃO
==========================

A conexão com o banco está em:

config/database.php


Configuração utilizada atualmente:

Host:
127.0.0.1

Porta:
3306

Banco:
negociacoes

Usuário:
root

Senha:
vazia


Caso a instalação local utilize configurações diferentes,
os dados devem ser alterados no arquivo:

config/database.php


6. ACESSO AO SISTEMA
====================

Com Apache e MySQL ativos, acesse:

http://localhost/sistema-negociacoes/login.php


7. PERFIS DE USUÁRIO
====================

O sistema possui três perfis:

ADMIN
SUPERVISOR
OPERADOR


ADMIN
-----

Possui acesso administrativo ao sistema.

Pode:

- Acessar o dashboard.
- Visualizar todos os atendimentos.
- Criar atendimentos.
- Editar atendimentos.
- Registrar pagamentos.
- Editar observações de atendimentos pagos.
- Acessar relatórios.
- Filtrar relatórios.
- Acessar auditoria.
- Gerenciar usuários.
- Criar usuários.
- Editar usuários.
- Ativar e desativar usuários.
- Redefinir senha de usuários.


SUPERVISOR
----------

Possui acesso operacional e gerencial.

Pode:

- Acessar o dashboard.
- Visualizar atendimentos de todos os operadores.
- Criar atendimentos.
- Editar atendimentos.
- Registrar pagamentos.
- Editar observações de atendimentos pagos.
- Acessar relatórios.
- Filtrar relatórios.
- Acessar auditoria.


OPERADOR
--------

Possui acesso aos próprios atendimentos.

Pode:

- Acessar o dashboard.
- Criar atendimentos.
- Visualizar os próprios atendimentos.
- Editar os próprios atendimentos enquanto não estiverem pagos.
- Registrar pagamento dos próprios atendimentos.
- Editar somente a observação dos próprios atendimentos após o pagamento.

O operador não pode:

- Visualizar atendimentos de outros operadores.
- Editar atendimentos de outros operadores.
- Registrar pagamento de atendimentos de outros operadores.
- Acessar relatórios administrativos.
- Acessar auditoria administrativa.


8. FLUXO DE ATENDIMENTO
=======================

O sistema atualmente trabalha com o conceito de ATENDIMENTO.

Cada ligação ou contato realizado com um associado gera um novo atendimento.

Um atendimento possui:

- Data do atendimento
- Inscrição
- Parcelas em atraso
- Valor negociado
- Valor recebido
- Data do pagamento
- Observações
- Operador responsável


9. INSCRIÇÃO
===========

A inscrição é informada manualmente pelo operador.

Atualmente não existe uma tabela específica de inscrições.

A mesma inscrição pode aparecer várias vezes no sistema.

Isso é esperado.

Exemplo:

Dia 01:
- Associado foi contatado.
- Não realizou pagamento.

Dia 10:
- Associado foi contatado novamente.
- Novo atendimento é registrado.

Os dois registros podem possuir a mesma inscrição.


10. PARCELAS EM ATRASO
======================

A quantidade de parcelas em atraso é informada manualmente.

O sistema exige no mínimo:

1 parcela em atraso.


11. VALOR NEGOCIADO
===================

O valor negociado representa o valor acordado durante o atendimento.

O valor deve ser maior que zero.


12. PAGAMENTO
============

O pagamento é registrado separadamente do atendimento.

Um atendimento pode possuir:

- Nenhum pagamento
- Um pagamento

O sistema não permite registrar dois pagamentos para o mesmo atendimento.


Campos do pagamento:

- Valor recebido
- Data do pagamento


IMPORTANTE:

O valor recebido pode ser maior que o valor negociado.

Isso pode acontecer devido a situações como:

- Juros
- Acréscimos
- Parcelas adicionais
- Outros valores pagos pelo associado

O sistema não bloqueia essa situação.


13. REGRAS APÓS O PAGAMENTO
===========================

Depois que um atendimento recebe um pagamento, os dados principais
do atendimento ficam bloqueados.

Não é mais possível alterar:

- Data do atendimento
- Inscrição
- Parcelas em atraso
- Valor negociado

Somente o campo:

- Observações

continua disponível para edição.


Exemplo:

Atendimento não pago:

- Data: editável
- Inscrição: editável
- Parcelas: editável
- Valor negociado: editável
- Observações: editável


Atendimento pago:

- Data: bloqueada
- Inscrição: bloqueada
- Parcelas: bloqueadas
- Valor negociado: bloqueado
- Valor recebido: bloqueado
- Data do pagamento: bloqueada
- Observações: editável


14. AUDITORIA
=============

As alterações importantes realizadas no sistema são registradas
na tabela:

auditoria


A auditoria registra informações como:

- Usuário responsável pela ação
- Ação realizada
- Tabela afetada
- ID do registro
- Dados anteriores
- Dados novos
- Data e hora da ação


Principais ações registradas:

CRIAR
EDITAR
EDITAR_OBSERVACAO
PAGAMENTO


A auditoria permite acompanhar alterações realizadas nos registros.


15. DASHBOARD
=============

O dashboard apresenta informações de acordo com o perfil do usuário.


Indicadores atuais:

- Total de atendimentos
- Atendimentos hoje
- Valor total negociado
- Total recebido
- Valor recebido hoje


Para OPERADOR:

Os valores consideram somente os próprios atendimentos.


Para SUPERVISOR e ADMIN:

Os valores consideram todos os atendimentos.


16. LISTAGEM DE ATENDIMENTOS
============================

A página:

atendimentos.php

apresenta os atendimentos registrados.


Informações exibidas:

- Data
- Inscrição
- Parcelas em atraso
- Valor negociado
- Valor recebido
- Data do pagamento
- Operadora
- Ação


Quando o atendimento não possui pagamento:

Valor recebido:
Não pago

Data do pagamento:
—


Quando o atendimento possui pagamento:

Valor recebido:
Valor registrado

Data do pagamento:
Data registrada


17. RELATÓRIOS
=============

A página:

relatorio.php

é acessível para:

- ADMIN
- SUPERVISOR


O relatório permite filtrar os atendimentos por:

- Mês
- Responsável/operadora


O relatório apresenta:

- Quantidade de atendimentos
- Total de parcelas em atraso
- Total negociado
- Total recebido
- Quantidade de pagamentos registrados


Também apresenta informações individuais dos atendimentos,
incluindo os dados de pagamento.


18. SEGURANÇA
============

O sistema utiliza algumas medidas básicas de segurança.


Autenticação:

O usuário precisa estar autenticado para acessar as páginas
protegidas.


Controle de acesso:

As permissões são verificadas de acordo com o perfil do usuário.


Controle de propriedade:

OPERADORES só podem acessar seus próprios atendimentos.


Senhas:

As senhas são armazenadas utilizando password_hash().

A verificação utiliza password_verify().


CSRF:

Formulários que realizam operações importantes utilizam tokens CSRF.


SQL:

As operações com dados fornecidos pelo usuário utilizam
prepared statements através do PDO.


Sessão:

A sessão utiliza:

- session.use_strict_mode
- session.use_only_cookies
- HttpOnly
- SameSite=Lax


19. ESTRUTURA PRINCIPAL DO PROJETO
==================================

sistema-negociacoes/
│
├── assets/
│   └── style.css
│
├── config/
│   ├── database.php
│   ├── auth.php
│   └── bootstrap.php
│
├── index.php
│
├── login.php
├── logout.php
│
├── novo_atendimento.php
├── atendimentos.php
├── editar_atendimento.php
├── pagamento_atendimento.php
│
├── relatorio.php
├── auditoria.php
│
├── usuarios.php
├── novo_usuario.php
├── editar_usuario.php
├── redefinir_senha_usuario.php
├── alternar_status_usuario.php
├── minha_conta.php
│
├── clientes.php
│
├── negociacao.php
├── negociacoes.php
├── editar_negociacao.php
├── pagamento.php
│
├── database.sql
└── README-XAMPP.txt


20. ARQUIVOS PRINCIPAIS
=======================

index.php
----------

Dashboard principal do sistema.


login.php
---------

Tela de autenticação.


novo_atendimento.php
--------------------

Cadastro de um novo atendimento.


atendimentos.php
----------------

Lista dos atendimentos cadastrados.


editar_atendimento.php
----------------------

Edição dos dados de um atendimento.

Quando o atendimento já possui pagamento,
somente a observação pode ser alterada.


pagamento_atendimento.php
-------------------------

Registro do pagamento de um atendimento.


relatorio.php
-------------

Relatórios mensais com filtros.


auditoria.php
-------------

Consulta das ações registradas no sistema.


config/auth.php
---------------

Responsável por:

- Sessão
- Login
- Logout
- Controle de acesso
- CSRF
- Auditoria
- Validações


config/database.php
-------------------

Responsável pela conexão com o banco de dados.


config/bootstrap.php
--------------------

Carrega as configurações principais do sistema e funções auxiliares.


21. BANCO DE DADOS
==================

As principais tabelas utilizadas atualmente são:


usuarios
--------

Armazena os usuários do sistema.


perfis
------

Armazena os perfis:

- ADMIN
- SUPERVISOR
- OPERADOR


atendimentos
------------

Tabela principal do fluxo atual.

Armazena os registros de atendimento,
incluindo informações de pagamento.


auditoria
---------

Armazena o histórico das ações realizadas no sistema.


clientes
--------

Tabela mantida no projeto para compatibilidade com funcionalidades
anteriores.


negociacoes
-----------

Tabela do fluxo antigo de negociações.

Ela ainda existe no banco por compatibilidade com a versão anterior
do sistema, mas não faz parte do fluxo atual de atendimentos.


22. FUNCIONALIDADES LEGADAS
===========================

O projeto passou por uma mudança de conceito.

A versão anterior trabalhava principalmente com:

- Clientes
- Negociações
- Descontos
- Status de negociação
- Pagamentos vinculados à negociação


O fluxo atual trabalha com:

- Atendimentos
- Inscrição
- Parcelas em atraso
- Valor negociado
- Pagamento separado
- Auditoria


Por isso, alguns arquivos e tabelas antigas ainda permanecem
temporariamente no projeto.


Arquivos legados:

- negociacao.php
- negociacoes.php
- editar_negociacao.php
- pagamento.php
- clientes.php


Tabela legada:

- negociacoes


Esses recursos não devem ser utilizados para o fluxo atual
de atendimento.


23. FLUXO ATUAL DO SISTEMA
==========================

O fluxo principal é:


LOGIN
  |
  v
DASHBOARD
  |
  +----------------------+
  |                      |
  v                      v
NOVO ATENDIMENTO     ATENDIMENTOS
                         |
                         v
                    EDITAR
                         |
                         v
                  REGISTRAR PAGAMENTO
                         |
                         v
                  ATENDIMENTO PAGO
                         |
              +----------+----------+
              |                     |
              v                     v
        DADOS BLOQUEADOS       OBSERVAÇÃO
                              EDITÁVEL
              |
              v
          AUDITORIA
              |
              v
          RELATÓRIO


24. MANUTENÇÃO
==============

Antes de alterar qualquer arquivo:

1. Faça uma cópia do projeto.
2. Faça backup do banco de dados.
3. Faça as alterações.
4. Teste o fluxo afetado.
5. Verifique a auditoria quando aplicável.


25. BACKUP DO BANCO
===================

Recomenda-se realizar backups periódicos do banco:

negociacoes


O backup pode ser realizado pelo phpMyAdmin através da opção:

Exportar


26. ACESSO RÁPIDO
=================

Sistema:

http://localhost/sistema-negociacoes/login.php


phpMyAdmin:

http://localhost/phpmyadmin


Pasta do projeto:

C:\xampp\htdocs\sistema-negociacoes


27. OBSERVAÇÕES FINAIS
======================

O sistema foi desenvolvido para centralizar os registros de
atendimentos que anteriormente eram controlados através de
planilhas individuais.

O objetivo principal é permitir:

- Registro centralizado dos atendimentos.
- Controle por operador.
- Acompanhamento dos pagamentos.
- Histórico das alterações.
- Relatórios gerenciais.
- Visualização dos valores negociados e recebidos.
- Maior rastreabilidade das operações.


FLUXO PRINCIPAL ATUAL:

ATENDIMENTO
    ↓
VALOR NEGOCIADO
    ↓
PAGAMENTO
    ↓
HISTÓRICO/AUDITORIA
    ↓
RELATÓRIO

O registro do atendimento representa o contato realizado com o
associado, enquanto o pagamento é registrado separadamente.

Cada contato pode gerar um novo atendimento, mesmo quando a
inscrição já tenha aparecido anteriormente no sistema.