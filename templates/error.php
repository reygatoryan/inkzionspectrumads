<?php
// Get error details
$errorCode = http_response_code() ?: 500;
$errorTitle = $title ?? 'Error';
$errorMessage = $message ?? 'Something went wrong. Please try again later.';
$baseUrl = 'https://inkzion.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($errorCode . ' - ' . $errorTitle); ?> | Inkzion Spectrum Ads</title>
  <meta name="description" content="Error page - Inkzion Spectrum Ads">
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .error-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .error-card {
      background: white;
      border-radius: 24px;
      padding: 3rem 2rem;
      max-width: 600px;
      width: 100%;
      text-align: center;
      box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
    }
    
    .error-icon {
      width: 120px;
      height: 120px;
      margin: 0 auto 2rem;
      background: linear-gradient(135deg, rgba(43, 76, 82, 0.1), rgba(43, 76, 82, 0.1));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: #2B4C52;
    }
    
    .error-code {
      font-size: 4rem;
      font-weight: 900;
      background: linear-gradient(135deg, #2B4C52, #4A7C84);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 1rem;
      line-height: 1;
    }
    
    .error-title {
      font-size: 2rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 1rem;
    }
    
    .error-message {
      font-size: 1.1rem;
      color: #64748b;
      margin-bottom: 2rem;
      line-height: 1.6;
    }
    
    .error-actions {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.85rem 1.5rem;
      border-radius: 12px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.3s ease;
      font-size: 1rem;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #2B4C52, #4A7C84);
      color: white;
      box-shadow: 0 8px 24px rgba(43, 76, 82, 0.3);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 32px rgba(43, 76, 82, 0.4);
    }
    
    .btn-secondary {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #e2e8f0;
    }
    
    .btn-secondary:hover {
      background: #e2e8f0;
    }
    
    .error-help {
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 1px solid #e2e8f0;
    }
    
    .error-help p {
      color: #64748b;
      font-size: 0.95rem;
      margin-bottom: 0.5rem;
    }
    
    .error-help a {
      color: #2B4C52;
      text-decoration: none;
      font-weight: 600;
    }
    
    .error-help a:hover {
      text-decoration: underline;
    }
    
    @media (max-width: 768px) {
      .error-container {
        padding: 1rem;
      }
      
      .error-card {
        padding: 2rem 1.5rem;
      }
      
      .error-code {
        font-size: 3rem;
      }
      
      .error-title {
        font-size: 1.5rem;
      }
      
      .error-message {
        font-size: 1rem;
      }
      
      .error-actions {
        flex-direction: column;
      }
      
      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <div class="error-container">
    <div class="error-card">
      <div class="error-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      
      <div class="error-code"><?php echo htmlspecialchars($errorCode); ?></div>
      <h1 class="error-title"><?php echo htmlspecialchars($errorTitle); ?></h1>
      <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
      
      <div class="error-actions">
        <a href="javascript:history.back()" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Go Back
        </a>
        <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-primary">
          <i class="fas fa-home"></i> Go Home
        </a>
      </div>
      
      <div class="error-help">
        <p>If you believe this is an error, please contact us:</p>
        <p>
          <a href="mailto:inkzionspectrum.com">inkzionspectrum.com</a> | 
          <a href="tel:+639754263237">+639754263237</a>
        </p>
      </div>
    </div>
  </div>
</body>
</html>