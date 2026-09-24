import { existsSync, readFileSync, rmSync } from 'node:fs';
import { wp } from './support/site';

export default async function globalTeardown(): Promise<void> {
    if (! existsSync('./.auth/user.json')) {
        return;
    }

    const { username } = JSON.parse(readFileSync('./.auth/user.json', 'utf8'));

    // --reassign is not given: anything the run left behind is deleted with its author.
    wp('user', 'delete', username, '--yes');
    rmSync('./.auth', { recursive: true, force: true });
}
