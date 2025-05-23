// const resolveFunctions = require('../src/resolveFunctions.js');
// import resolveFunctions from '../src/resolveFunctions.js';
import FuncyStr from '../src/FuncyStr.js';
import { expect } from 'chai';

const cases = [
    {
        name: 'should resolve a simple PRONOUN function',
        test: {
            input: 'This is a {{PRONOUN|he|she|they}}.',
            params: { pronoun: 'he' }
        },
        result: "This is a he."
    },
    {
        name: 'should resolve a simple PLURAL function',
        test: {
            input: 'There is one {{PLURAL|apple|apples}}.',
            params: { plural: true }
        },
        result: "There is one apples."
    },
    {
        name: 'should resolve a replacement function that takes no inline values (CHAR_NAME)',
        test: {
            input: 'Hello, {{CHAR_NAME}}.',
            params: { char_name: 'John' }
        },
        result: "Hello, John."
    },
    {
        name: 'should resolve multiple and nested functions',
        test: {
            input: 'We see that {{PLURAL|this is|these are}} {{PRONOUN|{{PLURAL|a man|men}}|{{PLURAL|a woman|women}}}}.',
            params: { pronoun: 'he', plural: true }
        },
        result: 'We see that these are men.'
    },
    {
        name: 'should handle missing function definitions gracefully',
        test: {
            input: 'This is a {{UNKNOWN|arg1|arg2}}.',
            params: {}
        },
        result: 'This is a {{UNKNOWN|arg1|arg2}}.'
    },
    {
        name: 'should handle missing function definitions gracefully',
        test: {
            input: 'This is a nested {{PLURAL|{{UNKNOWN|arg1|arg2}}|plural}}.',
            params: { plural: false }
        },
        result: 'This is a nested {{UNKNOWN|arg1|arg2}}.'
    },
    {
        name: 'should handle empty strings',
        test: {
            input: '',
            params: {}
        },
        result: ''
    },
    {
        name: 'should handle strings without functions',
        test: {
            input: 'This is a plain string.',
            params: {}
        },
        result: 'This is a plain string.'
    },
    {
        name: 'should resolve multiple functions in a single string.',
        test: {
            input: '{{CHAR_NAME}} said {{PRONOUN|he wants|she wants|they want}} to go.',
            params: { pronoun: 'she', char_name: 'Alex' }
        },
        result: 'Alex said she wants to go.'
    },
    {
        name: 'should resolve functions with no parameters',
        test: {
            input: '{{PRONOUN|He|She|They}} greeted us.',
            params: {}
        },
        result: 'They greeted us.' // Default to 'they' by default
    },
    {
        name: 'should resolve function that outputs unbalanced brackets',
        test: {
            input: 'This is a {{BRACKOUTPUT}}string.',
            params: {}
        },
        result: 'This is a {{string.'
    },
    {
        name: 'should resolve function that outputs unbalanced brackets',
        test: {
            input: 'This is a {{BRACKOUTPUTEND}}string.',
            params: {}
        },
        result: 'This is a }}string.'
    },
    {
        name: 'should resolve functions that output another function',
        test: {
            input: 'This is a {{OUTPUTFUNC}}.',
            params: { m: true, plural: true }
        },
        result: 'This is a men.'
    },
    {
        name: 'should ignore single brackets',
        test: {
            input: 'This is a {PRONOUN|man|woman}.',
            params: { m: true }
        },
        result: 'This is a {PRONOUN|man|woman}.'
    },
    {
        name: 'should ignore single brackets in parameters',
        test: {
            input: 'This is a {{PRONOUN|man{he}|woman{she}}}.',
            params: { pronoun: 'she' }
        },
        result: 'This is a woman{she}.'
    },
    {
        name: 'should resolve function that outputs unbalanced brackets within other functions',
        test: {
            input: 'This is a {{PLURAL|{{BRACKOUTPUTEND}}|many}} string.',
            params: { plural: true }
        },
        result: 'This is a many string.'
    },
    {
        name: 'should resolve function that outputs unbalanced brackets within other functions, and keep the unbalanced brackets',
        test: {
            input: 'This is a {{PLURAL|{{BRACKOUTPUTEND}}|many}} string.',
            params: { plural: false }
        },
        result: 'This is a }} string.'
    }
]

describe('FuncyStr process', () => {
    const fstr = new FuncyStr({
        PRONOUN: (params, he, she, they) => params.pronoun === 'he' ? he : params.pronoun === 'she' ? she : they,
        PLURAL: (params, one, plural) => (params.plural ? plural : one),
        CHAR_NAME: (params) => params.char_name,
        BRACKOUTPUT: (params) => '{{',
        BRACKOUTPUTEND: (params) => '}}',
        OUTPUTFUNC: (params) => {
            return params.m ? '{{PLURAL|man|men}}' : '{{PLURAL|woman|women}}';
        }
    })

    // Loop through the test cases and run each one
    cases.forEach(({ name, test, result }) => {
        it(name, async () => {
            const { input, params } = test;
            expect( await fstr.process(input, params)).to.equal(result);
        });
    });

    // Special case for deeply nested functions
    it('should resolve deeply nested functions', async () => {
        const input = "This is a {{PLURAL|{{PRONOUN|{{PLURAL|man|men}}|{{PLURAL|woman|women}}}}|people}}.";
        expect(
            await fstr.process(
                input,
                { pronoun: 'she', plural: false }
            )
        ).to.equal("This is a woman.");

        expect(
            await fstr.process(
                input,
                { pronoun: 'they', plural: true }
            )
        ).to.equal("This is a people.");

        expect(
            await fstr.process(
                input,
                { pronoun: 'he', plural: false }
            )
        ).to.equal("This is a man.");
    });
});

describe('FuncyStr async functions', () => {
    const asyncFetchFromWikipedia = async (params, articlename) => {
        try {
            const response = await fetch(
                `https://en.wikipedia.org/w/rest.php/v1/search/page?q=${articlename}&limit=1`,
                {
                    method: 'GET',
                    headers: {
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Content-Type': 'application/json'
                    }
                }
            );
            if (!response.ok) {
                console.error(response);
                return '<NO RESULTS>';
            }
            const json = await response.json();
            return json.pages[0].title;
        } catch (error) {
            console.error(error);
            return '<ERROR>';
        }
    }
    const fstr = new FuncyStr({
        PRONOUN: (params, he, she, they) => params.pronoun === 'he' ? he : params.pronoun === 'she' ? she : they,
        ARTICLE_NAME: (params) => params.article_name,
        FETCHREMOTE: asyncFetchFromWikipedia
    });

    it('should resolve an async function', async () => {
        expect(await fstr.process('{{FETCHREMOTE|earth}}', {})).to.equal('Earth');
        expect(await fstr.process('{{FETCHREMOTE|func}}', {})).to.equal('Function (computer programming)');
    });

    it('should resolve an async function with other functions', async () => {
        expect(await fstr.process(
            '{{FETCHREMOTE|earth}} said {{PRONOUN|he|she|they}} wants to go.',
            { pronoun: 'they' }
        )).to.equal('Earth said they wants to go.');
    });

    it('should resolve an async function with a nested function', async () => {
        expect(await fstr.process(
            '{{FETCHREMOTE|{{ARTICLE_NAME}}}} fetches the first result from Wikipedia for "{{ARTICLE_NAME}}".',
            { article_name: 'func' }
        )).to.equal('Function (computer programming) fetches the first result from Wikipedia for "func".');
    });
});

describe('FuncyStr process demo string', () => {
    it('should resolve the demo string', async () => {
        const str = `Hello, {{uppercase|world}}!
            The length is {{length|Hello world}}. We can also try {{uppercase|{{reverse|detsen}} functions}}.
            This is {{UPPERCASE|{{NOTRECOGNIZED|one|two}}}}
            This is {{repeat|so |5}}cool!
            {{pronoun|He|She|They}} went to the store and bought {{pronoun|himself|herself|themselves}} groceries and carried them home.`;
        const fstr = new FuncyStr({
            PRONOUN: (params, he, she, they) => params.pronoun === 'he' ? he : params.pronoun === 'she' ? she : they,
            UPPERCASE: (params, text) => text.toUpperCase(),
            LENGTH: (params, text) => text.length.toString(),
            REVERSE: (params, text) => text.split('').reverse().join(''),
            REPEAT: (params, text, times) => text.repeat(parseInt(times)),
        });

        expect(await fstr.process(str, { pronoun: 'they' })).to.equal(
            `Hello, WORLD!
            The length is 11. We can also try NESTED FUNCTIONS.
            This is {{NOTRECOGNIZED|ONE|TWO}}
            This is so so so so so cool!
            They went to the store and bought themselves groceries and carried them home.`
        );

        expect(await fstr.process(str, { pronoun: 'she' })).to.equal(
            `Hello, WORLD!
            The length is 11. We can also try NESTED FUNCTIONS.
            This is {{NOTRECOGNIZED|ONE|TWO}}
            This is so so so so so cool!
            She went to the store and bought herself groceries and carried them home.`
        );
    });
});
