import argparse
import json
from PIL import Image
import torch
import numpy as np
from math import ceil
from train import check_gpu
from torchvision import models

def arg_parser():
    parser = argparse.ArgumentParser(description="predict.py")
    parser.add_argument('--image', type=str, help='Path to image file for prediction.', required=True)
    parser.add_argument('--checkpoint', type=str, help='Path to checkpoint file as string.', required=True)
    parser.add_argument('--top_k', type=int, help='Choose top K matches as int.')
    parser.add_argument('--category_names', dest="category_names", action="store", default='cat_to_name.json')
    parser.add_argument('--gpu', default="gpu", action="store", dest="gpu")

    args = parser.parse_args()
    
    return args

def load_checkpoint(checkpoint_path):
    checkpoint = torch.load(checkpoint_path)
    
    model = models.vgg16(pretrained=True)
    model.name = "vgg16"    
    
    for param in model.parameters(): 
        param.requires_grad = False
    
    # Load from checkpoint
    model.class_to_idx = checkpoint['class_to_idx']
    model.classifier = checkpoint['classifier']
    model.load_state_dict(checkpoint['state_dict'])
    
    return model

def process_image(image_path):
    img = Image.open(image_path)

    original_width, original_height = img.size
    
    size = [256, 256**600] if original_width < original_height else [256**600, 256]
    
    img.thumbnail(size)
   
    center = original_width / 4, original_height / 4
    left, top, right, bottom = center[0] - (244 / 2), center[1] - (244 / 2), center[0] + (244 / 2), center[1] + (244 / 2)
    img = img.crop((left, top, right, bottom))

    numpy_img = np.array(img) / 255 

    # Normalize each color channel
    mean = [0.485, 0.456, 0.406]
    std = [0.229, 0.224, 0.225]
    numpy_img = (numpy_img - mean) / std
        
    # Set the color to the first channel
    numpy_img = numpy_img.transpose(2, 0, 1)
    
    return numpy_img

def predict(image_tensor, model, device, cat_to_name, topk=5):
    model.to(device)
    model.eval()

    image_tensor = torch.from_numpy(image_tensor).float().to(device)
    image_tensor.unsqueeze_(0)

    with torch.no_grad():
        output = model(image_tensor)

    probs, indices = torch.topk(torch.exp(output), topk)
    
    probs = probs.cpu().numpy()[0]
    indices = indices.cpu().numpy()[0]

    idx_to_class = {val: key for key, val in model.class_to_idx.items()}
    top_labels = [idx_to_class[lab] for lab in indices]
    top_flowers = [cat_to_name[lab] for lab in top_labels]
    
    return probs, top_labels, top_flowers

def print_probability(probs, flowers):
    for i, (flower, prob) in enumerate(zip(flowers, probs)):
        print("Rank {}: Flower: {}, Likelihood: {}%".format(i+1, flower, ceil(prob*100)))

def main():
    args = arg_parser()
    
    with open(args.category_names, 'r') as f:
        cat_to_name = json.load(f)

    model = load_checkpoint(args.checkpoint)
    
    image_tensor = process_image(args.image)
    
    device = check_gpu(gpu_arg=args.gpu)
    
    top_probs, top_labels, top_flowers = predict(image_tensor, model, device, cat_to_name, args.top_k)
    
    print_probability(top_probs, top_flowers)

if __name__ == '__main__':
    main()
