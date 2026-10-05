SISTEMA DE NEGOCIAÇÕES
=======================

Sistema web desenvolvido para centralizar o registro, acompanhamento e
controle de negociações realizadas pela equipe de atendimento.

O sistema foi desenvolvido para substituir o controle descentralizado
realizado através de planilhas individuais, permitindo que os colaboradores
registrem as informações diretamente em um banco de dados centralizado.


1. OBJETIVO
===========

O sistema tem como objetivo centralizar as negociações realizadas pelos
colaboradores da equipe de atendimento.

No processo anterior, cada funcionário mantinha sua própria planilha com
informações como:

- Cliente
- Data do contato
- Valor original
- Desconto
- Valor final
- Data combinada para pagamento
- Status da negociação
- Observações

Ao final do mês, essas informações precisavam ser consolidadas manualmente
para geração de um relatório.

Com o sistema, os dados passam a ser registrados diretamente em uma única
base de dados, permitindo:

- Centralização das informações
- Acompanhamento das negociações
- Controle de acesso por usuário
- Controle de pagamentos
- Relatórios
- Dashboard financeiro
- Histórico de alterações
- Maior segurança das informações


2. TECNOLOGIAS UTILIZADAS
=========================

Backend:
- PHP

Banco de dados:
- MySQL

Frontend:
- HTML5
- CSS3
- JavaScript

Servidor local:
- XAMPP
- Apache
- MySQL

Controle de versão:
- Git
- GitHub


3. REQUISITOS
=============

Para executar o sistema localmente é necessário possuir:

- Windows
- XAMPP
- Apache
- MySQL
- PHP incluído no XAMPP
- Navegador web
- Git (opcional, para versionamento)


4. ESTRUTURA DO PROJETO
=======================

sistema-negociacoes/
|
|-- assets/
|   `-- style.css
|
|-- config/
|   |-- auth.php
|   |-- bootstrap.php
|   `-- database.php
|
|-- auditoria.php
|-- clientes.php
|-- database.sql
|-- editar_negociacao.php
|-- index.php
|-- login.php
|-- logout.php
|-- negociacao.php
|-- negociacoes.php
|-- pagamento.php
|-- relatorio.php
|-- README-XAMPP.txt
`-- .gitignore


5. CONFIGURAÇÃO DO XAMPP
========================

O projeto foi desenvolvido para ser executado utilizando o XAMPP.

A pasta do projeto deve estar localizada dentro do diretório htdocs.

Caminho recomendado:

C:\xampp\htdocs\sistema-negociacoes


6. INICIANDO O XAMPP
====================

Abra o XAMPP Control Panel.

Inicie os seguintes serviços:

- Apache
- MySQL

Ambos devem estar em execução antes de acessar o sistema.


7. CONFIGURAÇÃO DO BANCO DE DADOS
=================================

Abra o phpMyAdmin através do endereço:

http://localhost/phpmyadmin


Crie um banco de dados chamado:

negociacoes


Depois importe o arquivo:

database.sql


O arquivo database.sql contém a estrutura necessária para criação do banco
e os dados iniciais do sistema.


8. CONFIGURAÇÃO DA CONEXÃO COM O BANCO
======================================

A conexão com o banco de dados está localizada em:

config/database.php


Configuração padrão utilizada no ambiente XAMPP:

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


Caso a instalação local do MySQL utilize outra configuração, os dados
podem ser alterados no arquivo config/database.php.


9. ACESSANDO O SISTEMA
======================

Depois de iniciar o Apache e o MySQL, acesse:

http://localhost/sistema-negociacoes/login.php


10. USUÁRIOS INICIAIS
=====================

O banco possui usuários iniciais para teste.

ADMIN
E-mail:
admin@local.test

Senha:
password


SUPERVISOR
E-mail:
supervisor@local.test

Senha:
password


OPERADOR
E-mail:
operador@local.test

Senha:
password


As credenciais acima são destinadas ao ambiente local de desenvolvimento
e testes.


11. PERFIS DE ACESSO
====================

O sistema possui três perfis de usuário:

- ADMIN
- SUPERVISOR
- OPERADOR


11.1 OPERADOR
-------------

O operador pode:

- Acessar o sistema
- Criar negociações
- Visualizar suas próprias negociações
- Editar suas próprias negociações
- Registrar pagamentos de suas próprias negociações
- Consultar informações relacionadas às suas negociações

O operador não pode:

- Visualizar negociações de outros operadores
- Editar negociações de outros operadores
- Registrar pagamentos de negociações de outros operadores
- Acessar o relatório administrativo
- Acessar a auditoria


11.2 SUPERVISOR
---------------

O supervisor pode:

- Acessar o sistema
- Criar negociações
- Visualizar todas as negociações
- Editar negociações
- Registrar pagamentos
- Consultar o relatório mensal
- Filtrar informações do relatório

O supervisor não possui acesso à auditoria administrativa.


11.3 ADMIN
----------

O administrador possui acesso completo ao sistema.

Pode:

- Acessar todas as negociações
- Criar negociações
- Editar negociações
- Registrar pagamentos
- Consultar relatórios
- Consultar auditoria
- Visualizar o histórico das alterações realizadas


12. FUNCIONALIDADES
===================


12.1 LOGIN E AUTENTICAÇÃO
-------------------------

O sistema possui autenticação através de e-mail e senha.

As senhas não são armazenadas diretamente no banco.

É utilizado:

password_hash()

para armazenamento seguro das senhas.

Durante o login é utilizado:

password_verify()

para validar a senha informada pelo usuário.


12.2 CONTROLE DE SESSÃO
-----------------------

Após o login, o sistema cria uma sessão para identificar o usuário.

O ID da sessão é regenerado após uma autenticação bem-sucedida.

O sistema também possui uma função de logout para encerramento da sessão.


12.3 CLIENTES
------------

O sistema permite cadastrar clientes.

Informações disponíveis:

- Nome
- Documento
- Telefone
- E-mail
- Status ativo/inativo


12.4 NEGOCIAÇÕES
----------------

É possível registrar uma nova negociação associada a um cliente e ao
usuário responsável.

Informações registradas:

- Cliente
- Usuário responsável
- Data do contato
- Valor original
- Desconto
- Valor final
- Data combinada
- Status
- Descrição
- Observações


12.5 CÁLCULO DO VALOR FINAL
---------------------------

O valor final da negociação é calculado automaticamente através da fórmula:

Valor final = Valor original - Desconto


O sistema também possui validações para impedir:

- Valor original negativo
- Desconto negativo
- Desconto maior que o valor original
- Valor final negativo


12.6 STATUS DAS NEGOCIAÇÕES
---------------------------

As negociações podem possuir os seguintes status:

PENDENTE
VENCIDO
PAGO
CANCELADO


PENDENTE:
A negociação foi registrada, mas o pagamento ainda não foi realizado.


VENCIDO:
A data combinada para pagamento passou e a negociação ainda está pendente.


PAGO:
O pagamento foi registrado corretamente.


CANCELADO:
Status disponível para negociações que forem canceladas.


12.7 ATUALIZAÇÃO AUTOMÁTICA DE NEGOCIAÇÕES VENCIDAS
----------------------------------------------------

O dashboard verifica automaticamente as negociações pendentes cuja data
combinada já passou.

Quando a data combinada é anterior à data atual, a negociação pode ser
atualizada automaticamente para:

VENCIDO


12.8 EDIÇÃO DE NEGOCIAÇÕES
--------------------------

Negociações que ainda não foram pagas podem ter seus principais dados
alterados.

Podem ser alterados:

- Cliente
- Data do contato
- Valor original
- Desconto
- Data combinada
- Descrição
- Observações


Depois que uma negociação é marcada como PAGO, os dados financeiros e
principais informações da negociação não podem mais ser alterados.

Para negociações pagas, somente a observação pode ser alterada.


12.9 PAGAMENTOS
---------------

O sistema possui uma funcionalidade específica para registrar pagamentos.

São registrados:

- Data do pagamento
- Valor pago

O sistema valida se o valor pago é exatamente igual ao valor final da
negociação.

Caso o valor esteja correto, a negociação passa para:

PAGO


O pagamento também gera um registro na auditoria.


12.10 DASHBOARD
---------------

O dashboard apresenta informações resumidas das negociações.

Indicadores disponíveis:

- Total de negociações
- Total de negociações pendentes
- Total de negociações vencidas
- Total de negociações pagas
- Valor bruto negociado
- Total de descontos aplicados
- Total negociado
- Total recebido
- Total pendente


O total pendente considera negociações que ainda estão:

- PENDENTE
- VENCIDO


12.11 ACESSO RÁPIDO
-------------------

O dashboard possui atalhos para:

- Ver negociações
- Nova negociação

Usuários ADMIN também possuem acesso rápido à:

- Auditoria


12.12 LISTAGEM DE NEGOCIAÇÕES
-----------------------------

A página de negociações apresenta as negociações registradas no sistema.

O conteúdo exibido respeita as permissões do usuário.

OPERADORES visualizam somente suas próprias negociações.

SUPERVISORES e ADMINS podem visualizar todas as negociações.


12.13 RELATÓRIO MENSAL
----------------------

O sistema possui um relatório administrativo para acompanhamento das
negociações.

O acesso é permitido para:

- ADMIN
- SUPERVISOR


O relatório possui filtros por:

- Mês
- Responsável
- Status


O relatório apresenta informações como:

- Data
- Cliente
- Responsável
- Valor original
- Desconto
- Valor final
- Data combinada
- Data do pagamento
- Valor pago
- Status


Também são apresentados totais financeiros:

- Quantidade de negociações
- Valor bruto
- Total de descontos
- Total negociado
- Total recebido
- Total pendente


12.14 AUDITORIA
---------------

O sistema possui um módulo de auditoria destinado ao acompanhamento das
alterações realizadas.

O acesso é restrito ao ADMIN.


A auditoria registra ações como:

- CRIAR
- EDITAR
- PAGAR


Para cada ação são registrados:

- Usuário responsável
- Ação realizada
- Tabela afetada
- ID do registro
- Dados anteriores
- Dados novos
- Data e hora


A auditoria permite filtrar os registros por:

- Data inicial
- Data final
- Usuário
- Ação
- ID do registro/negociação


Também é possível visualizar os detalhes dos dados anteriores e novos
armazenados em formato JSON.


13. SEGURANÇA
=============

O projeto possui mecanismos de segurança para proteger as operações
realizadas pelos usuários.


13.1 SENHAS
-----------

As senhas são armazenadas utilizando:

password_hash()


A validação durante o login utiliza:

password_verify()


As senhas não são armazenadas em texto puro.


13.2 PDO E PREPARED STATEMENTS
------------------------------

O sistema utiliza PDO para comunicação com o MySQL.

As operações que recebem dados externos utilizam prepared statements,
reduzindo o risco de SQL Injection.


13.3 CONTROLE DE PERMISSÕES
---------------------------

As permissões são verificadas no backend.

Não é considerado seguro apenas esconder botões na interface.

O sistema verifica o perfil e o usuário antes de permitir determinadas
operações.


13.4 CONTROLE DE ACESSO ÀS NEGOCIAÇÕES
--------------------------------------

Operadores só podem acessar negociações pertencentes a eles.

Supervisores e administradores podem acessar todas as negociações.


Essa verificação é realizada no backend através da função:

podeAcessarNegociacao()


13.5 PROTEÇÃO CSRF
------------------

As principais operações que alteram dados possuem proteção contra CSRF.

Atualmente protegidas:

- Criação de negociação
- Edição de negociação
- Registro de pagamento


O sistema gera um token associado à sessão e valida o token recebido no
POST.

Caso o token esteja ausente ou inválido, a requisição é bloqueada com:

Token CSRF inválido.


Os testes realizados confirmaram:

- Criação normal funciona
- Criação sem token é bloqueada
- Edição normal funciona
- Edição sem token é bloqueada
- Pagamento normal funciona
- Pagamento sem token é bloqueado


13.6 AUDITORIA
--------------

Alterações importantes realizadas no sistema são registradas na tabela
de auditoria.

Isso permite acompanhar quem realizou determinada ação e quais dados
foram alterados.


14. BANCO DE DADOS
==================

O banco de dados utilizado pelo sistema é:

negociacoes


Principais tabelas:


perfis
------

Armazena os perfis de acesso do sistema.

Perfis utilizados:

- ADMIN
- SUPERVISOR
- OPERADOR


usuarios
--------

Armazena os usuários do sistema.

Principais informações:

- ID
- Nome
- E-mail
- Senha
- Perfil
- Status ativo
- Data de criação
- Data de atualização


clientes
--------

Armazena os clientes cadastrados.

Principais informações:

- ID
- Nome
- Documento
- Telefone
- E-mail
- Status ativo
- Data de criação
- Data de atualização


negociacoes
-----------

Armazena as negociações realizadas.

Principais informações:

- ID
- Cliente
- Usuário responsável
- Data do contato
- Valor original
- Desconto
- Valor final
- Data combinada
- Data do pagamento
- Valor pago
- Status
- Descrição
- Observações
- Data de criação
- Data de atualização


auditoria
---------

Armazena o histórico das principais alterações realizadas no sistema.

Campos principais:

- ID
- Usuário
- Ação
- Tabela afetada
- ID do registro
- Dados anteriores
- Dados novos
- Data e hora


15. RELACIONAMENTOS DO BANCO
============================

O banco utiliza relacionamentos entre as tabelas.

Um perfil pode estar associado a vários usuários.

Um usuário pode possuir várias negociações.

Um cliente pode possuir várias negociações.

Uma negociação pertence a um cliente e a um usuário responsável.

A tabela de auditoria pode estar relacionada ao usuário que realizou a
ação registrada.


16. FLUXO PRINCIPAL DO SISTEMA
==============================

O fluxo principal pode ser representado da seguinte forma:


USUÁRIO
   |
   v
LOGIN
   |
   v
VALIDAÇÃO DE CREDENCIAIS
   |
   v
CRIAÇÃO DA SESSÃO
   |
   v
DASHBOARD
   |
   +----------------------+
   |                      |
   v                      v
CLIENTES              NEGOCIAÇÕES
                          |
                          v
                     NOVA NEGOCIAÇÃO
                          |
                          v
                       MYSQL
                          |
                          v
                     NEGOCIAÇÃO
                          |
             +------------+------------+
             |                         |
             v                         v
         EDITAR                    PAGAMENTO
             |                         |
             v                         v
           MYSQL                    PAGO
             |                         |
             +------------+------------+
                          |
                          v
                      AUDITORIA


17. ARQUITETURA BÁSICA
======================

O sistema segue uma arquitetura simples baseada em:

Frontend
   |
   v
PHP
   |
   v
PDO
   |
   v
MySQL


O navegador envia uma requisição para uma página PHP.

O PHP:

- valida o usuário
- valida permissões
- valida os dados
- executa operações no banco
- retorna o resultado para o navegador


18. ARQUIVOS PRINCIPAIS
=======================


config/database.php
-------------------

Responsável pela conexão com o banco de dados através do PDO.


config/auth.php
---------------

Responsável por:

- Sessão
- Login
- Logout
- Verificação de autenticação
- Verificação de perfis
- Controle de acesso às negociações
- Auditoria
- Token CSRF


config/bootstrap.php
--------------------

Arquivo utilizado para carregar as configurações e funções necessárias
ao funcionamento das páginas.


login.php
---------

Tela de login do sistema.


logout.php
----------

Responsável pelo encerramento da sessão.


index.php
---------

Dashboard principal.


clientes.php
------------

Cadastro e gerenciamento de clientes.


negociacao.php
--------------

Cadastro de novas negociações.


negociacoes.php
---------------

Listagem das negociações.


editar_negociacao.php
---------------------

Edição das negociações.


pagamento.php
-------------

Registro de pagamentos.


relatorio.php
-------------

Relatório mensal das negociações.


auditoria.php
-------------

Consulta do histórico de auditoria.


database.sql
------------

Script para criação e inicialização do banco de dados.


assets/style.css
----------------

Arquivo de estilos do sistema.


19. INSTALAÇÃO PASSO A PASSO
============================

1. Instale o XAMPP.

2. Inicie o Apache e o MySQL.

3. Copie a pasta do projeto para:

C:\xampp\htdocs\sistema-negociacoes

4. Abra:

http://localhost/phpmyadmin

5. Crie o banco:

negociacoes

6. Importe:

database.sql

7. Confira a configuração em:

config/database.php

8. Acesse:

http://localhost/sistema-negociacoes/login.php

9. Utilize um dos usuários de teste.


20. DESENVOLVIMENTO LOCAL
=========================

O projeto atualmente foi preparado para funcionar em ambiente local
utilizando XAMPP.

Não é necessário configurar Docker para executar a versão atual.

O projeto utiliza:

Apache
+
PHP
+
MySQL


21. GIT E GITHUB
================

O projeto utiliza Git para controle de versão.

O repositório local é inicializado dentro da pasta:

C:\xampp\htdocs\sistema-negociacoes


Comandos básicos:

Verificar estado:

git status


Adicionar arquivos:

git add .


Criar commit:

git commit -m "descrição da alteração"


Enviar para o GitHub:

git push


22. .GITIGNORE
==============

O projeto possui um arquivo .gitignore para evitar o versionamento de
arquivos desnecessários ou potencialmente sensíveis.

Entre os itens ignorados estão:

- .env
- arquivos temporários
- logs
- configurações de IDE
- arquivos específicos do sistema operacional


23. TESTES REALIZADOS
=====================

Durante o desenvolvimento foram realizados testes das principais
funcionalidades.


Autenticação:
- Login de ADMIN testado
- Login de SUPERVISOR testado
- Login de OPERADOR testado


Clientes:
- Cadastro de cliente testado


Negociações:
- Criação de negociação testada
- Cálculo de desconto testado
- Valor final testado
- Edição testada
- Controle de status testado


Pagamentos:
- Registro de pagamento testado
- Validação do valor pago testada
- Alteração para status PAGO testada


Dashboard:
- Totais financeiros testados
- Negociações pendentes testadas
- Negociações vencidas testadas
- Negociações pagas testadas


Relatório:
- Filtro por mês testado
- Filtro por responsável testado
- Filtro por status testado


Permissões:
- Restrição de negociações entre operadores testada
- Acesso de supervisor testado
- Acesso administrativo testado
- Restrição da auditoria testada


Auditoria:
- CRIAR testado
- EDITAR testado
- PAGAR testado
- Filtros testados
- Visualização dos dados anteriores e novos testada


CSRF:
- Criação com token testada
- Criação sem token bloqueada
- Edição com token testada
- Edição sem token bloqueada
- Pagamento com token testado
- Pagamento sem token bloqueado


24. LIMITAÇÕES ATUAIS
=====================

O sistema ainda está em desenvolvimento.

Algumas funcionalidades poderão ser aprimoradas posteriormente, como:

- Gerenciamento completo de usuários pelo ADMIN
- Melhorias no gerenciamento de clientes
- Controle mais avançado de parcelas
- Melhorias nos relatórios
- Exportação de relatórios
- Melhorias na interface
- Melhorias de responsividade
- Melhorias adicionais de segurança
- Configuração de ambiente para produção
- Separação de configurações sensíveis através de variáveis de ambiente
- Melhorias no gerenciamento de sessões
- Novos indicadores e gráficos


25. PRÓXIMAS ETAPAS
===================

As próximas etapas planejadas incluem:


SEGURANÇA
---------

- Revisão completa das permissões
- Validações adicionais
- Melhorias na segurança da sessão
- Revisão de operações acessíveis diretamente por URL
- Melhorias na proteção das operações


GERENCIAMENTO DE USUÁRIOS
-------------------------

Criar uma área administrativa para:

- Criar usuários
- Editar usuários
- Ativar/desativar usuários
- Definir perfil


CLIENTES
--------

Evoluir o gerenciamento de clientes.


FINANCEIRO
----------

Avaliar:

- Parcelamento
- Controle de parcelas
- Datas de vencimento
- Pagamentos parciais
- Histórico financeiro


RELATÓRIOS
----------

Avaliar:

- Exportação
- PDF
- Excel
- Novos filtros
- Novos indicadores


INTERFACE
---------

A interface poderá receber melhorias de:

- Organização
- Cores
- Componentes
- Responsividade
- Experiência do usuário


26. STATUS DO PROJETO
=====================

Projeto em desenvolvimento.

A versão atual já possui:

- Autenticação
- Controle de perfis
- Controle de permissões
- Cadastro de clientes
- Cadastro de negociações
- Edição de negociações
- Controle de pagamentos
- Controle de negociações vencidas
- Dashboard financeiro
- Relatório mensal
- Auditoria
- Proteção CSRF
- Hash de senhas
- Prepared statements
- Controle de acesso por usuário


27. OBSERVAÇÃO SOBRE AMBIENTE DE PRODUÇÃO
=========================================

A configuração atual foi criada para desenvolvimento local.

Antes de utilizar o sistema em produção, devem ser realizadas configurações
adicionais de segurança, infraestrutura e banco de dados.

Entre elas:

- Utilização de credenciais seguras
- Senhas diferentes das credenciais de teste
- Variáveis de ambiente
- HTTPS
- Configuração segura de cookies
- Configuração adequada de sessão
- Backup do banco
- Controle de acesso ao servidor
- Monitoramento
- Configuração adequada do MySQL
- Revisão completa de segurança


28. AUTORIA
===========

Projeto desenvolvido para estudo e aplicação prática de desenvolvimento
web utilizando PHP e MySQL.

Tecnologias principais:

PHP
MySQL
HTML
CSS
JavaScript
XAMPP
Git
GitHub


29. LICENÇA
===========

Projeto destinado a fins de desenvolvimento e estudo.

A definição de uma licença específica poderá ser adicionada posteriormente.