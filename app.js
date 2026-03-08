import express from "express";
import dotenv from "dotenv";
import fetch from "node-fetch";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

dotenv.config();

const app = express();
const PIXAZO_API_KEY = process.env.PIXAZO_API_KEY;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(__dirname));

// Serve basic test UI
app.get("/", (req, res) => {
  res.sendFile(path.join(__dirname, "index.html"));
});

// Pixazo /generate-image endpoint
app.post("/generate-image", async (req, res) => {
  const prompt = req.body.prompt?.trim();
  if (!prompt) return res.json({ success: false, error: "Prompt required" });

  try {
    const pixazoUrl = "https://gateway.pixazo.ai/getImage/v1/getSDXLImage";

    const response = await fetch(pixazoUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Ocp-Apim-Subscription-Key": PIXAZO_API_KEY,
      },
      body: JSON.stringify({
        prompt: prompt,
        negative_prompt: "low-quality, blurry, abstract, cartoon",
        height: 1024,
        width: 1024,
        num_steps: 20,
        guidance_scale: 5,
        seed: 42
      }),
    });

    const data = await response.json();
    console.log("Full Pixazo Response:", data); // 🔍 debug

    if (!data?.imageUrl) {
      return res.json({ success: false, error: "No image returned from Pixazo" });
    }

    return res.json({ success: true, imageUrl: data.imageUrl });

  } catch (err) {
    console.error("Pixazo error:", err);
    return res.json({ success: false, error: err.message });
  }
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Server running at http://localhost:${PORT}`);
});