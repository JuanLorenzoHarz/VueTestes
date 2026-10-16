"""Integração real: requer Docker Compose e dados de demonstração no MySQL."""
import json
import os
import unittest
from urllib.error import HTTPError
from urllib.request import Request, urlopen

BASE = os.environ.get('NEXUS_API_URL', 'http://127.0.0.1:8080').rstrip('/')


def request(path, method='GET', body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = Request(BASE + path, data=data, method=method, headers={'Content-Type': 'application/json'})
    try:
        with urlopen(req, timeout=12) as res:
            return res.status, json.load(res)
    except HTTPError as exc:
        return exc.code, json.loads(exc.read())


class ApiRestTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        try:
            status, body = request('/api/health')
        except OSError as exc:
            raise RuntimeError(f'API não acessível em {BASE}. Inicie docker compose up -d --build.') from exc
        assert status == 200 and body['status'] == 'ok', body

    def test_pesquisa_e_perfil(self):
        status, data = request('/api/empresas?busca=Aurora')
        self.assertEqual(status, 200)
        self.assertTrue(any(item['id'] == 1 for item in data['dados']))
        status, data = request('/api/empresas/1')
        self.assertEqual(status, 200)
        self.assertEqual(data['dados']['codigo_demo'], 'DEMO-EMP-001')
        self.assertEqual(len(data['dados']['socios']), 2)
        _, company = request('/api/empresas/2')
        self.assertEqual(company['dados']['razao_social'], 'Horizonte Logística S.A.')
        self.assertEqual(company['dados']['registros_juridicos'][0]['tipo'], 'Ação cível')

    def test_arvore_e_relatorio(self):
        status, data = request('/api/empresas/1/arvore')
        self.assertEqual(status, 200)
        self.assertEqual(len(data['dados']['socios']), 2)
        self.assertTrue(any(p['outras_empresas'] for p in data['dados']['socios']))
        status, data = request('/api/empresas/1/relatorio')
        self.assertEqual(status, 200)
        self.assertTrue(data['dados']['dados_ficticios'])
        self.assertEqual(data['dados']['sumario']['socios'], 2)
        self.assertNotIn('score_consolidado', data['dados'])

    def test_nao_encontrado_e_busca_grande(self):
        for path in ['/api/empresas/999999', '/api/empresas/999999/arvore', '/api/empresas/999999/relatorio']:
            with self.subTest(path=path):
                status, data = request(path)
                self.assertEqual(status, 404)
                self.assertIn('erro', data)
        status, _ = request('/api/empresas?busca=' + 'x' * 101)
        self.assertEqual(status, 422)

    def test_verificacao_automatica_por_selecao(self):
        _, before = request('/api/consultas')
        previous_ids = {item['id'] for item in before['dados']}
        status, data = request('/api/empresas/1/verificacoes', 'POST')
        self.assertEqual(status, 201)
        self.assertEqual(data['dados']['consulta']['empresa_id'], 1)
        self.assertEqual(data['dados']['fontes_atualizadas'], 2)
        self.assertEqual(data['dados']['empresa']['id'], 1)
        status, history = request('/api/consultas')
        self.assertEqual(status, 200)
        self.assertTrue(any(item['id'] == data['dados']['consulta']['id'] for item in history['dados']))
        new_entries = [item for item in history['dados'] if item['id'] not in previous_ids]
        self.assertEqual(len(new_entries), 1)
        self.assertEqual(new_entries[0]['empresa_id'], 1)
        self.assertRegex(new_entries[0]['criada_em'], r'^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$')
        # Leituras de perfil, árvore e relatório não devem criar consultas.
        for suffix in ['', '/arvore', '/relatorio']:
            self.assertEqual(request('/api/empresas/1' + suffix)[0], 200)
        self.assertEqual(request('/api/consultas')[1], history)
        status, notfound = request('/api/empresas/999999/verificacoes', 'POST')
        self.assertEqual(status, 404)

    def test_consulta_e_validacao(self):
        status, _ = request('/api/consultas', 'POST', {'empresa_id': 'abc'})
        self.assertEqual(status, 422)
        status, _ = request('/api/consultas', 'POST', {'empresa_id': 999999})
        self.assertEqual(status, 404)
        status, created = request('/api/consultas', 'POST', {'empresa_id': 1})
        self.assertEqual(status, 201)
        self.assertEqual(created['dados']['empresa_id'], 1)
        status, history = request('/api/consultas')
        self.assertEqual(status, 200)
        self.assertTrue(any(item['id'] == created['dados']['id'] for item in history['dados']))

    def test_integracoes_http_e_preservacao_de_snapshot(self):
        path = '/api/empresas/1/integracoes'
        status, _ = request(path, 'POST', {'cenarios': {'bureau-a': 'normal'}})
        self.assertEqual(status, 422)
        status, _ = request(path, 'POST', {'cenarios': {'bureau-a': 'normal', 'bureau-b': 'invalido'}})
        self.assertEqual(status, 422)
        status, normal = request(path, 'POST', {'cenarios': {'bureau-a': 'normal', 'bureau-b': 'normal'}})
        self.assertEqual(status, 201)
        self.assertEqual({r['situacao'] for r in normal['dados']['resultados']}, {'sucesso'})
        status, company = request('/api/empresas/1')
        self.assertEqual(status, 200)
        before = {item['fonte']: (item['score'], item['pendencias'], item['protestos'], item['atualizado_em']) for item in company['dados']['indicadores']}
        status, failed = request(path, 'POST', {'cenarios': {'bureau-a': 'falha', 'bureau-b': 'timeout'}})
        self.assertEqual(status, 201)
        self.assertEqual({r['situacao'] for r in failed['dados']['resultados']}, {'erro_http', 'timeout'})
        status, company = request('/api/empresas/1')
        after = {item['fonte']: (item['score'], item['pendencias'], item['protestos'], item['atualizado_em']) for item in company['dados']['indicadores']}
        self.assertEqual(before, after, 'Falha apagou ou alterou snapshot anterior')
        status, history = request(path)
        self.assertEqual(status, 200)
        self.assertEqual(history['dados'][0]['id'], failed['dados']['id'])
        status, report = request('/api/empresas/1/relatorio')
        self.assertEqual(status, 200)
        self.assertIn('bureau-a', ' '.join(report['dados']['avisos']))


if __name__ == '__main__':
    unittest.main(verbosity=2)
