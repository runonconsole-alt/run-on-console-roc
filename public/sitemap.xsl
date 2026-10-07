<?xml version="1.0" encoding="UTF-8"?>
<!-- Run On Console: shows the sitemaps as a readable table in a browser.
     Search engines read the XML itself and ignore this stylesheet. -->
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:sm="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:roc="https://runonconsole.com/ns/sitemap">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html lang="en">
      <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <meta name="robots" content="noindex"/>
        <title>Sitemap | Run On Console</title>
        <style>
          body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#f8fafc;color:#0f172a}
          header{background:linear-gradient(135deg,#064e3b,#047857);color:#fff;padding:28px 20px}
          header h1{margin:0 0 6px;font-size:24px}
          header p{margin:0;color:#a7f3d0;font-size:14px}
          main{max-width:1100px;margin:0 auto;padding:20px 16px 48px}
          .count{font-size:13px;color:#475569;margin:0 0 12px}
          table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;font-size:14px}
          th{background:#ecfdf5;color:#065f46;text-align:left;padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
          td{padding:10px 12px;border-top:1px solid #f1f5f9;vertical-align:top}
          td.name{font-weight:600}
          td a{color:#047857;text-decoration:none;word-break:break-all}
          td a:hover{text-decoration:underline}
          td.date{white-space:nowrap;color:#64748b;font-size:13px}
          .back{display:inline-block;margin-bottom:12px;color:#047857;font-weight:600;text-decoration:none}
        </style>
      </head>
      <body>
        <header>
          <h1>Run On Console sitemap</h1>
          <p>Every public page on runonconsole.com. Search engines read this file to find and refresh pages.</p>
        </header>
        <main>
          <xsl:if test="sm:urlset"><a class="back" href="/sitemap.xml">← All sitemaps</a></xsl:if>
          <xsl:apply-templates select="sm:sitemapindex|sm:urlset"/>
        </main>
      </body>
    </html>
  </xsl:template>

  <xsl:template match="sm:sitemapindex">
    <p class="count"><xsl:value-of select="count(sm:sitemap)"/> sitemaps</p>
    <table>
      <tr><th>Sitemap</th><th>Address</th><th>Last updated</th></tr>
      <xsl:for-each select="sm:sitemap">
        <tr>
          <td class="name"><xsl:value-of select="roc:title"/></td>
          <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
          <td class="date"><xsl:value-of select="substring(sm:lastmod,1,10)"/></td>
        </tr>
      </xsl:for-each>
    </table>
  </xsl:template>

  <xsl:template match="sm:urlset">
    <p class="count"><xsl:value-of select="count(sm:url)"/> pages</p>
    <table>
      <tr><th>Page</th><th>Address</th><th>Last updated</th></tr>
      <xsl:for-each select="sm:url">
        <tr>
          <td class="name">
            <xsl:choose>
              <xsl:when test="roc:title"><xsl:value-of select="roc:title"/></xsl:when>
              <xsl:otherwise>—</xsl:otherwise>
            </xsl:choose>
          </td>
          <td><a href="{sm:loc}"><xsl:value-of select="sm:loc"/></a></td>
          <td class="date"><xsl:value-of select="substring(sm:lastmod,1,10)"/></td>
        </tr>
      </xsl:for-each>
    </table>
  </xsl:template>
</xsl:stylesheet>
