import argparse
import torch
from os.path import isdir
from torchvision import datasets, transforms, models
from torch import nn, optim

def parse_args():
    parser = argparse.ArgumentParser(description="Train.py")
    parser.add_argument('--arch', dest="arch", action="store", default="vgg16", type=str)
    parser.add_argument('--save_dir', dest="save_dir", action="store", default="./checkpoint.pth")
    parser.add_argument('--learning_rate', dest="learning_rate", action="store", default=0.001, type=float)
    parser.add_argument('--hidden_units', dest="hidden_units", action="store", default=120, type=int)
    parser.add_argument('--epochs', dest="epochs", action="store", type=int, default=1)
    parser.add_argument('--gpu', dest="gpu", action="store", default="gpu")
    return parser.parse_args()

def get_transformer(data_dir, train=True):
    transform = transforms.Compose([transforms.RandomRotation(30),
                                    transforms.RandomResizedCrop(224),
                                    transforms.RandomHorizontalFlip(),
                                    transforms.ToTensor(),
                                    transforms.Normalize([0.485, 0.456, 0.406],
                                                         [0.229, 0.224, 0.225])])

    dataset = datasets.ImageFolder(data_dir, transform=transform)
    loader = torch.utils.data.DataLoader(dataset, batch_size=50, shuffle=train)

    return loader

def check_gpu(gpu_arg):
    if not gpu_arg:
        return torch.device("cpu")

    device = torch.device("cuda:0" if torch.cuda.is_available() else "cpu")

    if device == "cpu":
        print("CUDA was not found on device, using CPU instead.")
    return device

def create_model(architecture="vgg16"):
    model = models.vgg16(pretrained=True)
    model.name = "vgg16"

    for param in model.parameters():
        param.requires_grad = False

    return model

def modify_classifier(model, hidden_units):
    classifier = nn.Sequential(nn.Linear(25088, 120),
                               nn.ReLU(),
                               nn.Dropout(0.5),
                               nn.Linear(120, 90),
                               nn.ReLU(),
                               nn.Linear(90, 70),
                               nn.ReLU(),
                               nn.Linear(70, 102),
                               nn.LogSoftmax(dim=1))

    model.classifier = classifier
    return classifier

def validation(model, testloader, criterion, device):
    test_loss, accuracy = 0, 0

    for inputs, labels in testloader:
        inputs, labels = inputs.to(device), labels.to(device)

        output = model.forward(inputs)
        test_loss += criterion(output, labels).item()

        ps = torch.exp(output)
        equality = (labels.data == ps.max(dim=1)[1])
        accuracy += equality.type(torch.FloatTensor).mean()

    return test_loss, accuracy

def train_model(model, trainloader, validloader, device, criterion, optimizer, epochs, print_every):
    steps = 0

    for e in range(epochs):
        running_loss = 0
        model.train()

        for inputs, labels in trainloader:
            steps += 1
            inputs, labels = inputs.to(device), labels.to(device)

            optimizer.zero_grad()
            outputs = model.forward(inputs)
            loss = criterion(outputs, labels)
            loss.backward()
            optimizer.step()

            running_loss += loss.item()

            if steps % print_every == 0:
                model.eval()

                with torch.no_grad():
                    valid_loss, accuracy = validation(model, validloader, criterion)

                print("Epoch: {}/{} | ".format(e+1, epochs),
                      "Training Loss: {:.4f} | ".format(running_loss/print_every),
                      "Validation Loss: {:.4f} | ".format(valid_loss/len(validloader)),
                      "Validation Accuracy: {:.4f}".format(accuracy/len(validloader)))

                running_loss = 0
                model.train()

def validate_model_accuracy(model, testloader, device):
    correct, total = 0, 0

    with torch.no_grad():
        model.eval()
        for data in testloader:
            images, labels = data
            images, labels = images.to(device), labels.to(device)
            outputs = model(images)
            _, predicted = torch.max(outputs.data, 1)
            total += labels.size(0)
            correct += (predicted == labels).sum().item()

    print('Accuracy on test images is: %d%%' % (100 * correct / total))

def save_checkpoint(model, save_dir, train_data):
    if type(save_dir) == type(None):
        print("Model checkpoint directory not specified, model will not be saved.")
    else:
        if isdir(save_dir):
            model.class_to_idx = train_data.class_to_idx
            checkpoint = {'architecture': model.name,
                          'classifier': model.classifier,
                          'class_to_idx': model.class_to_idx,
                          'state_dict': model.state_dict()}

            torch.save(checkpoint, 'my_checkpoint.pth')
        else:
            print("Directory not found, model will not be saved.")

def main():
    args = parse_args()

    data_dir = 'flowers'
    train_dir = data_dir + '/train'
    valid_dir = data_dir + '/valid'
    test_dir = data_dir + '/test'

    trainloader = get_transformer(train_dir)
    validloader = get_transformer(valid_dir, train=False)
    testloader = get_transformer(test_dir, train=False)

    model = create_model(architecture=args.arch)
    model.classifier = modify_classifier(model, hidden_units=args.hidden_units)

    device = check_gpu(gpu_arg=args.gpu)
    model.to(device)

    learning_rate = args.learning_rate if args.learning_rate else 0.001
    criterion = nn.NLLLoss()
    optimizer = optim.Adam(model.classifier.parameters(), lr=learning_rate)

    print_every = 30
    steps = 0

    train_model(model, trainloader, validloader, device, criterion, optimizer, args.epochs, print_every)

    print("\nTraining process is completed!!")

    validate_model_accuracy(model, testloader, device)

    save_checkpoint(model, args.save_dir, train_data)

if __name__ == '__main__':
    main()
