import assert from 'node:assert/strict';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import tseslint from 'typescript-eslint';

test('projectService checks a file admitted by an allowDefaultProject glob', () => {
    const root = mkdtempSync(join(tmpdir(), 'ascanio-tooling-'));

    try {
        writeFileSync(join(root, 'tsconfig.json'), JSON.stringify({
            compilerOptions: { strict: true },
            include: ['src/**/*.ts'],
        }));
        const source = 'const ready: boolean = true;';
        const filePath = join(root, 'probe.ts');
        writeFileSync(filePath, source);

        const result = tseslint.parser.parseForESLint(source, {
            filePath,
            tsconfigRootDir: root,
            projectService: { allowDefaultProject: ['*.ts'] },
        });

        assert.equal(result.ast.type, 'Program');
        assert.ok(result.services.program);
        assert.equal(result.services.program.getSemanticDiagnostics().length, 0);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
