PHP 8.5.12 CLI ZTS, Linux x86_64, Swoole 6.3.0RC1 e psampler 0.7.0, com correção nativa de lifecycle dos FDs. ELF estático, sem strip, 138.608.352 bytes. Publicação dos mesmos bytes já homologados, sem rebuild ou substituição do PHP instalado. Assets anteriores preservados.

SHA-256: `50d874e9b06959973ec2873019acf0b09c3a350a8e60adce7e6f7f05ea59cd7f`.

Validação contra spechshop/libspech, branch Optimizer, commit `0c79736a23f538061a8ea613b69936513523b99d`: 17/17 comandos funcionais, 2/2 controles Socket, 24/24 lifecycle tradicional, 24/24 nativo e 48/48 arquivos PHP na sintaxe. Zero FDs retidos aos 50 ms com objetos fechados ainda referenciados e reactor persistente. Cinco casos adicionais TLS/streams importados aprovados, incluindo cancelamento de leitura TLS e reutilização de descritores.

Limites: não é certificação geral de Swoole. Não executados PHPT completo, matriz TLS/DTLS/mTLS, threads, escrita bloqueada, hooks completos, Windows ou soak de produção. A CI da libspech será atualizada e executada depois de verificar o download desta release; não se declara aqui aprovação antecipada da CI.

Procedência: checkout pcg729 base `0b0be527d05b5166bc014cde2dfe1c5358b34105`, mais integração local não commitada dos patches. A tag identifica a base, não contém sozinha todos os fontes do build e não garante reprodução bit a bit. `runtime-manifest.json` registra hashes do patch, integração e manifesto nativo. Nenhuma alteração local preexistente do pcg729 foi commitada ou modificada nesta publicação. Não foram incorporados patches C/C++ à libspech.

Correção baseada na avaliação da PR Swoole #6274, head `861a0dffbf5f97671b2d8772078e07d4db120cde`, adaptada ao Swoole 6.3.0RC1 Linux no sistema de build pcg729. Separa fechamento físico da destruição diferida, remove o socket do reactor antes de fechar e invalida seu FD para impedir double close após reutilização.

Verifique `sha256sum -c SHA256SUMS` antes de executar o asset. A identidade homologada é estabelecida pelo SHA-256 acima.
