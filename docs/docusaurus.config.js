// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).
// There are various equivalent ways to declare your Docusaurus config.
// See: https://docusaurus.io/docs/api/docusaurus-config

import { themes as prismThemes } from 'prism-react-renderer';

const baseUrl = '/wp-github-updater/';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'WordPress GitHub Updater',
  tagline: 'Native WordPress updates from GitHub releases, with settings that fit your plugin or theme.',
  favicon: 'img/favicon-light.ico',
  headTags: [
    {
      tagName: 'link',
      attributes: {
        rel: 'icon',
        href: `${baseUrl}img/favicon-dark.ico`,
        media: '(prefers-color-scheme: dark)',
      },
    },
  ],

  // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
  future: {
    v4: true, // Improve compatibility with the upcoming Docusaurus v4
  },

  // Set the production url of your site here
  url: 'https://scottoffen.github.io',
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl,

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'scottoffen', // Usually your GitHub org/user name.
  projectName: 'wp-github-updater', // Usually your repo name.

  onBrokenLinks: 'throw',
  onBrokenAnchors: 'throw',
  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'throw',
    },
  },

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            'https://github.com/scottoffen/wp-github-updater/tree/main/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themes: ['@docusaurus/theme-mermaid'],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      colorMode: {
        respectPrefersColorScheme: true,
      },
      navbar: {
        title: 'WordPress GitHub Updater',
        logo: {
          alt: 'WordPress GitHub Updater Logo',
          src: 'img/logo-light.svg',
          srcDark: 'img/logo-dark.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'docsSidebar',
            position: 'left',
            label: 'Documentation',
          },
          {
            href: 'https://github.com/scottoffen/wp-github-updater',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Documentation',
            items: [
              {
                label: 'Getting Started',
                to: '/',
              },
              {
                label: 'Packaging and Releases',
                to: '/packaging-and-releases',
              },
              {
                label: 'Private Repositories',
                to: '/private-repositories',
              },
              {
                label: 'Updater-Provided Settings Section',
                to: '/settings-section',
              },
              {
                label: 'How Updates Work',
                to: '/how-updates-work',
              },
              {
                label: 'Troubleshooting',
                to: '/troubleshooting',
              },
              {
                label: 'Reference',
                to: '/reference',
              },
            ],
          },
          {
            title: 'Community',
            items: [
              {
                label: 'Discussions',
                href: 'https://github.com/scottoffen/wp-github-updater/discussions',
              },
            ],
          },
          {
            title: 'Project',
            items: [
              {
                label: 'Contributing Guide',
                href: 'https://github.com/scottoffen/wp-github-updater/blob/main/.github/contributing.md',
              },
              {
                label: 'Code of Conduct',
                href: 'https://github.com/scottoffen/wp-github-updater/blob/main/.github/code_of_conduct.md',
              },
              {
                label: 'GitHub',
                href: 'https://github.com/scottoffen/wp-github-updater',
              },
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} Scott Offen`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
      },
    }),
};

export default config;
