import json
import os
from pathlib import Path
import socket
import subprocess
import time
from urllib.error import HTTPError
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]

proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{port}', 'router.php'],
    cwd=ROOT / 'mock-bureaus', stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
)

try:
    origin = f'http://127.0.0.1:{port}'
    for _ in range(35):
        try:
            with urlopen(origin + '/health', timeout=1) as res:
                assert json.load(res)['status'] == 'ok'
            break
        except OSError:
            time.sleep(.08)
    else:
        raise AssertionError('Mock HTTP não iniciou')

    for bureau, codigo, score in [
        ('bureau-a', 'DEMO-EMP-001', 740),
        ('bureau-b', 'DEMO-EMP-001', 698),
        ('bureau-b', 'DEMO-EMP-003', None),
    ]:
        with urlopen(f'{origin}/v1/{bureau}/empresas/{codigo}?cenario=normal') as res:
            data = json.load(res)
            assert res.status == 200
            assert data['score'] == score
            assert data['dados_ficticios'] is True
            assert data['codigo_demo'] == codigo
    print('OK: respostas normais de ambos os bureaus e score ausente')

    start = time.monotonic()
    with urlopen(origin + '/v1/bureau-a/empresas/DEMO-EMP-002?cenario=lento') as res:
        assert res.status == 200
    assert time.monotonic() - start >= .65
    print('OK: resposta com latência simulada')

    try:
        urlopen(origin + '/v1/bureau-a/empresas/DEMO-EMP-001?cenario=falha')
        raise AssertionError('Esperado HTTP 503')
    except HTTPError as e:
        assert e.code == 503
        assert json.loads(e.read())['erro']
    print('OK: erro HTTP 503')

    try:
        urlopen(origin + '/v1/bureau-b/empresas/DEMO-EMP-002?cenario=timeout', timeout=1.6)
        raise AssertionError('Esperado timeout')
    except (TimeoutError, OSError) as e:
        assert 'timed out' in str(e).lower()
    print('OK: timeout no cliente')

finally:
    proc.terminate()
    try:
        proc.wait(timeout=2)
    except subprocess.TimeoutExpired:
        proc.kill()
        proc.wait()
