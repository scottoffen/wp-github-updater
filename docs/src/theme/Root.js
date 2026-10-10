import {useEffect, useState} from 'react';
import Head from '@docusaurus/Head';
import useBaseUrl from '@docusaurus/useBaseUrl';

export default function Root({children}) {
  const lightIcon = useBaseUrl('/img/favicon-light.ico');
  const darkIcon = useBaseUrl('/img/favicon-dark.ico');
  const [isDark, setIsDark] = useState(false);

  useEffect(() => {
    const preference = window.matchMedia(
      '(prefers-color-scheme: dark)',
    );

    const updateTheme = () => {
      setIsDark(preference.matches);
    };

    updateTheme();
    preference.addEventListener('change', updateTheme);

    return () => {
      preference.removeEventListener('change', updateTheme);
    };
  }, []);

  const favicon = `${isDark ? darkIcon : lightIcon}?v=2`;

  return (
    <>
      {children}
      <Head>
        <link rel="icon" type="image/x-icon" href={favicon} />
      </Head>
    </>
  );
}
