#!/bin/bash

# Script Master de Testes - libspech
# Executa toda a suite de testes (unitários + integração)

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
RESET='\033[0m'
BOLD='\033[1m'

# Limpa a tela
clear

# Header
echo -e "\n"
echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════════════════╗${RESET}"
echo -e "${CYAN}${BOLD}║                                                      ║${RESET}"
echo -e "${CYAN}${BOLD}║          Suite Completa de Testes - libspech         ║${RESET}"
echo -e "${CYAN}${BOLD}║                                                      ║${RESET}"
echo -e "${CYAN}${BOLD}║  Unitários: 99 testes                                ║${RESET}"
echo -e "${CYAN}${BOLD}║  Integração: 5 instâncias paralelas                  ║${RESET}"
echo -e "${CYAN}${BOLD}║                                                      ║${RESET}"
echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════════════════╝${RESET}"
echo -e "\n"

# Variáveis de controle
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
START_TIME=$(date +%s)

# Função para imprimir separador
print_separator() {
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
}

# Função para executar um teste
run_test() {
    local test_name=$1
    local test_command=$2
    local test_count=$3

    echo -e "\n${YELLOW}🧪 Executando: ${WHITE}${BOLD}${test_name}${RESET}"
    echo -e "${BLUE}Comando: ${test_command}${RESET}\n"

    print_separator

    # Executa o teste e captura o exit code
    $test_command
    local exit_code=$?

    print_separator

    # Atualiza contadores
    TOTAL_TESTS=$((TOTAL_TESTS + test_count))

    if [ $exit_code -eq 0 ]; then
        echo -e "\n${GREEN}${BOLD}✓ ${test_name}: PASSOU (${test_count} testes)${RESET}\n"
        PASSED_TESTS=$((PASSED_TESTS + test_count))
        return 0
    else
        echo -e "\n${RED}${BOLD}✗ ${test_name}: FALHOU${RESET}\n"
        FAILED_TESTS=$((FAILED_TESTS + test_count))
        return 1
    fi
}

# ============================================================================
# EXECUÇÃO DOS TESTES
# ============================================================================

echo -e "${CYAN}Iniciando suite de testes...${RESET}\n"

# Teste 1: RTP Channel (48 testes)
run_test "RTP Channel Tests" "php run_tests.php" 48
RTP_RESULT=$?

# Teste 2: Media Channel (51 testes)
run_test "Media Channel Tests" "php run_media_tests.php" 51
MEDIA_RESULT=$?

# Verifica se deve executar teste de integração
echo -e "\n${YELLOW}Deseja executar o teste de integração paralela? (5 instâncias simultâneas)${RESET}"
echo -e "${CYAN}Este teste requer servidor SIP configurado e pode demorar ~60s${RESET}"
echo -e "${WHITE}Digite 's' para SIM ou qualquer outra tecla para NÃO: ${RESET}"
read -t 10 -n 1 RUN_INTEGRATION
echo -e "\n"

INTEGRATION_RESULT=0
if [[ $RUN_INTEGRATION == "s" || $RUN_INTEGRATION == "S" ]]; then
    # Teste 3: Integração Paralela
    run_test "Integração Paralela (5x)" "php run_parallel_test.php" 5
    INTEGRATION_RESULT=$?
    TOTAL_TESTS=$((TOTAL_TESTS + 5))
    if [ $INTEGRATION_RESULT -eq 0 ]; then
        PASSED_TESTS=$((PASSED_TESTS + 5))
    else
        FAILED_TESTS=$((FAILED_TESTS + 5))
    fi
else
    echo -e "${YELLOW}⏭  Pulando teste de integração${RESET}\n"
fi

# ============================================================================
# RESUMO FINAL
# ============================================================================

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo -e "\n"
print_separator
echo -e "${WHITE}${BOLD}  RESUMO GERAL${RESET}"
print_separator
echo -e "\n"

echo -e "  ${WHITE}Total de testes executados:${RESET} ${BOLD}${TOTAL_TESTS}${RESET}"
echo -e "  ${GREEN}Testes aprovados:${RESET}          ${BOLD}${PASSED_TESTS}${RESET}"

if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "  ${RED}Testes reprovados:${RESET}         ${BOLD}${FAILED_TESTS}${RESET}"
fi

# Calcula taxa de sucesso
if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$(awk "BEGIN {printf \"%.1f\", ($PASSED_TESTS/$TOTAL_TESTS)*100}")
    echo -e "  ${CYAN}Taxa de sucesso:${RESET}           ${BOLD}${SUCCESS_RATE}%${RESET}"
fi

echo -e "  ${BLUE}Tempo total:${RESET}               ${BOLD}${DURATION}s${RESET}"

echo -e "\n"
print_separator
echo -e "\n"

# Resultado individual de cada suite
echo -e "${WHITE}${BOLD}Detalhamento por Suite:${RESET}\n"

if [ $RTP_RESULT -eq 0 ]; then
    echo -e "  ${GREEN}✓ RTP Channel Tests (48)${RESET}"
else
    echo -e "  ${RED}✗ RTP Channel Tests (48)${RESET}"
fi

if [ $MEDIA_RESULT -eq 0 ]; then
    echo -e "  ${GREEN}✓ Media Channel Tests (51)${RESET}"
else
    echo -e "  ${RED}✗ Media Channel Tests (51)${RESET}"
fi

if [[ $RUN_INTEGRATION == "s" || $RUN_INTEGRATION == "S" ]]; then
    if [ $INTEGRATION_RESULT -eq 0 ]; then
        echo -e "  ${GREEN}✓ Integração Paralela (5)${RESET}"
    else
        echo -e "  ${RED}✗ Integração Paralela (5)${RESET}"
    fi
fi

echo -e "\n"
print_separator
echo -e "\n"

# Mensagem final
if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}${BOLD}✓ ✓ ✓  TODOS OS TESTES PASSARAM!  ✓ ✓ ✓${RESET}\n"
    echo -e "${CYAN}🎉 Parabéns! O sistema está funcionando perfeitamente.${RESET}\n"
    exit 0
else
    echo -e "${RED}${BOLD}✗ ✗ ✗  ALGUNS TESTES FALHARAM  ✗ ✗ ✗${RESET}\n"
    echo -e "${YELLOW}💡 Dica: Revise os logs para mais detalhes${RESET}\n"
    exit 1
fi
